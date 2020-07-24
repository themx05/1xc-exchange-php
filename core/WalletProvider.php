<?php

namespace Providers{

    use ConversionProvider;
    use PDO;
    use stdClass;

    class WalletProvider{
        public $client;
        public const WALLET_BUSINESS = "business";
        public const TX_COMMISSION = "commission";
        public const TX_DEPOSIT = "deposit";
        public const TX_WITHDRAW = "withdraw";
        public const TX_NORMAL = "normal";
        
        public function __construct(PDO $client){
            $this->client = $client;
        }

        private function generateSignature(){
            $prefix = "1XC";
            $items = "1234567890";
            $generated = $prefix;
            $length = strlen($items);

            for($i=0; $i<8; $i++){
                $rand_pos = rand(0, $length-1);
                $generated .= $items[$rand_pos];
            }
            return $generated;
        }

        public function saveRegistrationFeeInstant(string $userId, string $method, string $reference, float $amount, string $currency, int $time){
            $query = "INSERT INTO RegistrationFeeTransaction(id, data) VALUES (?,?)";
            $stmt = $this->client->prepare($query);
            $id = generateHash();
            $data = [
                'id' => $id,
                'user' => $userId,
                'method' => $method,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'paymentDate' => $time
            ];

            $previous_entry = $this->getRegistrationFeeEntry($userId);

            if(isset($previous_entry)){
                return "";
            }
            
            if($stmt->execute([$id, json_encode($data)])){
                return $id;
            }
            return "";
        }

        public function getRegistrationFeeEntry(string $userId){
            $query = "SELECT * FROM RegistrationFeeTransaction WHERE JSON_EXTRACT(data, '$.user') = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$userId]) && $stmt->rowCount() > 0){
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                return json_decode($item['data'], true);
            }
            return null;
        }

        public function createWallet(string $type = "business", string $currency = 'XOF', float $initialBalance = 0, string $userId){
            $signature = $this->generateSignature();
            $data = [
                'id' => $signature,
                'userId' => $userId,
                'type' => $type,
                'balance' => [
                    'amount' => $initialBalance,
                    'currency' => $currency
                ],
                'creationDate' => time(),
            ];

            $query = "INSERT INTO Wallets (id,data) VALUES(?,?)";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$signature, json_encode($data)])){
                return $signature;
            }
            return "";
        }

        public function getWalletById(string $id){
            $query = "SELECT * FROM Wallets WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$id]) && $stmt->rowCount() > 0){
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                $item['data'] = json_decode($item['data'], true);
                return $item['data'];
            }
            return null;
        }

        public function getWalletByUser(string $user){
            $query = "SELECT * FROM Wallets WHERE JSON_EXTRACT(data,'$.userId') = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$user]) && $stmt->rowCount() > 0){
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                $item['data'] = json_decode($item['data'], true);
                return $item['data'];
            }
            return null;
        }

        public function saveUserDeposit(string $wallet, string $method, string $reference, float $amount, string $currency){
            $data = [
                'id' => generateHash(),
                'walletId' => $wallet,
                'method' => $method,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'date' => time()
            ];
            $previous = $this->getDepositByReference($reference);
            if(isset($previous) && isset($previous['id']) && $previous['method'] === $method){
                return "";
            }
            $query = "INSERT INTO WalletDeposit(id, data) VALUES (?,?)";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$data['id'], json_encode($data)])){
                return $data['id'];
            }
            return "";
        }

        public function getDepositByReference(string $reference){
            $query = "SELECT * FROM  WalletDeposit WHERE JSON_EXTRACT(data,'$.reference') = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$reference]) && $stmt->rowCount() > 0){
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                return json_decode($item['data'], true);
            }
            return null;
        }

        public function deposit(string $walletId, float $amount = 0, $currency= "XOF",string $memo="", string $type = WalletProvider::TX_NORMAL){
            if($amount > 0){
                $wallet = $this->getWalletById($walletId);

                if(!isset($wallet)){
                    return "";
                }

                if($currency !== $wallet['balance']['currency']){
                    $converter = new ConversionProvider();
                    $result = $converter->convert([
                        'source' => $currency,
                        'dest' => $wallet['balance']['currency'],
                        'amount' => $amount
                    ]);

                    if($result !== -1){
                        $converted = $result['converted'];
                        $amount = $converted;
                    }
                    else{
                        return "";
                    }
                }

                $wallet['balance']['amount'] +=$amount;
                
                $query = 'UPDATE Wallets SET data = ? WHERE id = ?';
                $stmt = $this->client->prepare($query);

                if($stmt->execute([json_encode($wallet), $walletId])){
                    /// Handle History Management.
                    return $this->createHistory($walletId, $amount, $memo, $type);
                }
            }
            return "";
        }

        public function withdraw(string $walletId, float $amount = 0, $currency = "XOF", string $memo=""){
            if($amount > 0){
                $wallet = $this->getWalletById($walletId);

                if(!isset($wallet)){
                    return "";
                }

                if($currency !== $wallet['balance']['currency']){
                    $converter = new ConversionProvider();
                    $result = $converter->convert([
                        'source' => $currency,
                        'dest' => $wallet['balance']['currency'],
                        'amount' => $amount
                    ]);

                    if($result !== -1){
                        $converted = $result['converted'];
                        $amount = $converted;
                    }
                    else{
                        return "";
                    }
                }

                if($wallet['balance']['amount'] < $amount){
                    return "";
                }

                $wallet['balance']['amount'] -=$amount;
                
                $query = 'UPDATE Wallets SET data = ? WHERE id = ?';
                $stmt = $this->client->prepare($query);
                if($stmt->execute([json_encode($wallet), $walletId])){
                    /// Handle History Management.
                    return $this->createHistory($walletId, -1*$amount, $memo);
                }
            }
            return "";
        }

        public function createHistory(string $walletId, float $amount, string $memo, string $type = WalletProvider::TX_NORMAL){
            $wallet = $this->getWalletById($walletId);
            $data = [
                'id' => generateHash(),
                'wallet' => $walletId,
                'type' => $type,
                'amount' => $amount,
                'currency' => $wallet['balance']['currency'],
                'memo' => $memo,
                'creationDate' => time()
            ];
            $query = "INSERT INTO WalletHistory(id, data) VALUES(?,?)";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$data['id'], json_encode($data)])){
                return $data['id'];
            }
            return "";
        }

        public function getWallets(){
            $query = "SELECT * FROM Wallets ORDER BY JSON_EXTRACT(data,'$.creationDate') ASC";
            $stmt = $this->client->prepare($query);
            if($stmt->execute() && $stmt->rowCount() > 0){
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $parsed = [];
                foreach($items as $key => $value){
                    $value['data'] = json_decode($value['data']);
                    array_push($parsed,$value['data']);
                }
                return $parsed;
            }
            return [];
        }

        public function getHistoriesByWallet(string $wallet){
            $query = "SELECT * FROM WalletHistory WHERE JSON_EXTRACT(data,'$.wallet') = ?";
            $stmt = $this->client->prepare($query);

            if($stmt->execute([$wallet]) && $stmt->rowCount() > 0){
                $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $parsed = [];
                foreach($raw as $key => $value){
                    array_push($parsed, json_decode($value['data']));
                }
                return $parsed;
            }
            return [];
        }

        public function getHistoryById(string $id){
            $query = "SELECT * FROM WalletHistory WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$id]) && $stmt->rowCount() > 0){
                $items = $stmt->fetch(PDO::FETCH_ASSOC);
                $parsed = [];
                foreach($items as $key => $value){
                    $value['data'] = json_decode($value['data']);
                    array_push($parsed,$value['data']);
                }
                return $parsed;
            }
            return [];
        }
    }
}

?>