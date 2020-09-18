<?php

namespace Core;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\MethodAccount;
use PDO;
use stdClass;
use Utils\Config;
use Utils\Utils;

class MethodAccountProvider extends ServiceClient{

    public function getAccountById(string $id): ?MethodAccount{
        try{
            $url = Config::apiUrl()."/system/methodaccounts/".$id;
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
            ];

            $request = new Request("GET", $url, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success){
                $account = new MethodAccount($body->data);
                return $account;
            }
            return null;
        }
        catch(Exception $e){
            if($this->logger){
                $this->logger->error($e->getMessage());
            }
            return null;
        }
    }

    public function getAccountByType(string $type):?MethodAccount{
        global $logger;
        try{
            $url = Config::apiUrl()."/system/methodaccounts?type=".$type;
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
            ];

            $request = new Request("GET", $url, $headers);
            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success && isset($body->data[0])){
                $account = new MethodAccount($body->data[0]);
                return $account;
            }
            return null;
        }
        catch(Exception $e){
            if($this->logger){
                $this->logger->error($e->getMessage());
            }
            return null;
        }
    }

    public function getPerfectMoney(){
        $method = $this->getAccountByType(MethodAccount::TYPE_PERFECT);
        if($method != null){
           return $method->detailsAsPerfectMoney();
        }
        return null;
    }

    public function getFedaPay(){
        $method = $this->getAccountByType(MethodAccount::TYPE_FEDAPAY);
        if($method != null){
            return $method->detailsAsFedapay();
        }
        return null;
    }

    public function getCoinbase(){
        $method = $this->getAccountByType(MethodAccount::TYPE_COINBASE);
        if($method != null){
            return $method->detailsAsCoinbase();
        }
        return null;
    }
}

?>