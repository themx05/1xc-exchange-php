<?php
/**
 * 
 */
namespace Core;

use CoinbaseUtils;
use Models\Method;
use PDO;
use Utils\Coinbase\Coinbase;

class BalanceProvider extends ServiceClient{

    public function getBalance(Method $method){
        $maProvider = new MethodAccountProvider($this->client);
        if($method->type === Method::TYPE_PERFECTMONEY){
            $pmSpecs = $maProvider->getPerfectMoney();
            if($pmSpecs !== null){
                $pmAccount = new PerfectMoney($pmSpecs->accountId,$pmSpecs->passphrase);
                $address = $method->getAddress();
                return $pmAccount->getBalance($address);
            }
        }
        else if($method->type === Method::TYPE_MTN){
            return -1;
        }
        else if($method->type === Method::TYPE_MOOV){
            return -1;
        }
        else if($method->category === Method::CATEGORY_CRYPTO){
            $currency = $method->getCurrency();
            $coinbaseSpecs = $maProvider->getCoinbase();
            $coinbaseUtils = new Coinbase($coinbaseSpecs, $this->logger);
            $compatible_accounts = $coinbaseUtils->getWalletsByCurrency($currency);

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