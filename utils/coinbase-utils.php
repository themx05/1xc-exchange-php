<?php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\CoinbaseAccount;
use Psr\Log\LoggerInterface;

class SendTransaction{
    public $to;
    public $amount;
    public $currency;
    public $fee;
    public $description;
    public $idem;

    public function __construct(
        string $to, 
        float $amount, 
        string $currency,
        string $description = ""){
        
        $this->to = $to;
        $this->amount = ceil($amount * 100000000) / 100000000;
        $this->currency = $currency;
        $this->description = $description;
    }

    public function setFee($fee){
        $this->fee = ceil($fee * 100000000) / 100000000;
    }

    public function __toString(){
        $data = [];
        $data['type'] = 'send';
        $data['to'] = $this->to;
        $data['amount'] = number_format($this->amount, 8);
        $data['currency'] = $this->currency;
        
        if(isset($this->description)){
            $data['description'] = $this->description;
        }

        if(isset($this->fee)){
            $data['fee'] = number_format($this->fee, 8);
        }
        
        if(isset($this->idem)){
            $data['idem'] = $this->idem;
        }
        return json_encode($data);
    }
}

class CoinbaseUtils{
        public $account;
        public $logger;

        public function __construct(CoinbaseAccount $account, LoggerInterface $logger){
            $this->account = $account;
            $this->logger = $logger;
        }

        public function cbAccessSign(string $method, string $path, int $timestamp, string $body = ""){
            $method = strtoupper($method);
            $secret = $this->account->privateKey;
            $unsigned_cb = $timestamp.$method.$path.$body;
            $cb_sign = hash_hmac("sha256",$unsigned_cb, $secret);
            return $cb_sign;
        }

        public function getAccounts(){
            $timestamp = time();
            $path = "/v2/accounts";
            $cb_sign = $this->cbAccessSign("get",$path, $timestamp);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $cb_sign,
                'CB-ACCESS-TIMESTAMP' => $timestamp
            ];

            $request = new Request("GET", CB_URL.$path, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();
            $accounts = json_decode($strbody)->data;
            if(isset($accounts)){
                return $accounts;
            }
            return [];
        }

        public function getAccount(string $id){
            $timestamp = time();
            $path = "/v2/accounts/$id";
            $cb_sign = $this->cbAccessSign("get",$path, $timestamp);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $cb_sign,
                'CB-ACCESS-TIMESTAMP' => $timestamp
            ];

            $request = new Request("GET", CB_URL.$path, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();
            $account = json_decode($strbody)->data;
            if(isset($account)){
                return $account;
            }
            return null;
        }

        public function getWallets(){
            $accounts = $this->getAccounts();

            $selectedAccounts = [];

            foreach($accounts as $key => $value){
                if($value->type === "wallet"){
                    array_push($selectedAccounts, $value);
                }
            }

            return $selectedAccounts;
        }

        public function getVaults(){
            $accounts = $this->getAccounts();
            $selectedAccounts = [];

            foreach($accounts as $key => $value){
                if($value->type === "vault"){
                    array_push($selectedAccounts, $value);
                }
            }

            return $selectedAccounts;
        }

        public function getWalletsByCurrency(string $crypto){
            $accounts = $this->getWallets();
            $selectedAccounts = [];

            foreach($accounts as $key => $value){
                if($value->currency->code === $crypto){
                    array_push($selectedAccounts, $value);
                }
            }

            return $selectedAccounts;
        }

        public function createAddress(string $name, string $accountId){
            $path = "/v2/accounts/$accountId/addresses";
            $body = json_encode([
                'name' => $name
            ]);
            $timestamp = time();
            $signature = $this->cbAccessSign("post",$path, $timestamp,$body);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $signature,
                'CB-ACCESS-TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json;charset=utf-8'
            ];

            $request = new Request("POST", CB_URL.$path, $headers,$body);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();

            $json_response = json_decode($strbody);
            if(isset($json_response->data)){
                $this->logger->info("Successfully generated address for account $accountId. Address: ".json_encode($json_response->data));
                return $json_response->data;
            }

            $this->logger->info("Failed to generate address for account $accountId");
            return null;
        }

        public function getAddress(string $account, string $address){
            $timestamp = time();
            $path = "/v2/accounts/$account/addresses/$address";
            $cb_sign = $this->cbAccessSign("get",$path, $timestamp);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $cb_sign,
                'CB-ACCESS-TIMESTAMP' => $timestamp
            ];

            $request = new Request("GET", CB_URL.$path, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();
            $address = json_decode($strbody)->data;
            if(isset($address)){
                return $address;
            }
            return null;
        }

        public function createAccountSend(string $accountId, SendTransaction $send){
            $path = "/v2/accounts/$accountId/transactions";
            $body = $send->__toString();

            $timestamp = time();
            $signature = $this->cbAccessSign("post",$path, $timestamp,$body);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $signature,
                'CB-ACCESS-TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json;charset=utf-8'
            ];

            $request = new Request("POST", CB_URL.$path, $headers,$body);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();

            $json_response = json_decode($strbody);
            if(isset($json_response->data)){
                $this->logger->info("Successfully generated send transaction for account $accountId. Transaction id ".json_encode($json_response->data->id));
                return $json_response->data;
            }

            $this->logger->info("Failed to create transaction for account $accountId");
            return null;
        }

        public function getTransaction(string $accountId, string $trans){
            $timestamp = time();
            $path = "/v2/accounts/$accountId/transactions/$trans";
            $cb_sign = $this->cbAccessSign("get",$path, $timestamp);

            $headers = [
                'CB-ACCESS-KEY' => $this->account->publicKey,
                'CB-ACCESS-SIGN' => $cb_sign,
                'CB-ACCESS-TIMESTAMP' => $timestamp
            ];

            $request = new Request("GET", CB_URL.$path, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $strbody = $response->getBody()->__toString();
            $tx = json_decode($strbody)->data;
            if(isset($tx)){
                return $tx;
            }
            return null;
        }
    }

?>