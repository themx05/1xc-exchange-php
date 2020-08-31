<?php
/**
 * 
 */
namespace Core;

    use CoinbaseUtils;
use Models\Method;
use PDO;

class BalanceProvider{
    public PDO $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function getBalance(array $method){
        global $logger;
        $maProvider = new MethodAccountProvider($this->client);
        if($method['type'] === Method::TYPE_PERFECTMONEY){
            $pmSpecs = $maProvider->getPerfectMoney();
            $pmAccount = new PerfectMoney($pmSpecs['details']['accountId'],$pmSpecs['details']['passphrase']);
            $address = PaymentGateway::addressFromMethod(json_decode(json_encode($method)));
            return $pmAccount->getBalance($address);
        }
        else if($method['type'] === Method::TYPE_MTN){
            return -1;
        }
        else if($method['type'] === Method::TYPE_MOOV){
            return -1;
        }
        else if($method['category'] === Method::CATEGORY_CRYPTO){
            $currency = PaymentGateway::getCurrencyFromMethod(json_decode(json_encode($method)));
            $coinbaseSpecs = $maProvider->getCoinbase();
            $coinbaseUtils = new CoinbaseUtils($coinbaseSpecs, $logger);
            $compatible_accounts = $coinbaseUtils->getWalletsByCurrency(strtoupper($currency));

            if(!empty($compatible_accounts)){
                $wallet = $compatible_accounts[0];
                $balance = doubleval($wallet->balance->amount);
                return $balance;
            }

            else{
                return -1;
            }
            
        }
        return -1;
    }
}

?>