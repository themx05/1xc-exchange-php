<?php

namespace Core;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\Wallet;
use Models\WalletHistory;
use Utils\Config;

class WalletProvider extends ServiceClient{

    public function getWalletById(string $id): ?Wallet{
        try{
            $url = Config::apiUrl()."/wallets/".$id;
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
                $profile = new Wallet($body->data);
                return $profile;
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

    public function getBusinessWalletByUser(string $user): ?Wallet{
        try{
            $url = Config::apiUrl()."/wallets/?type=business&user=".$user;
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
                $profile = new Wallet($body->data[0]);
                return $profile;
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

    /**
     * Returns the main wallet of a user.
     */
    public function getMainUserWallet(string $user){
        try{
            $url = Config::apiUrl()."/wallets/?principal=true&user=".$user;
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
                $profile = new Wallet($body->data[0]);
                return $profile;
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

    public function deposit(string $walletId, float $amount = 0, $currency= "XOF",string $memo="", string $type = WalletHistory::TYPE_NORMAL){
        try{
            $url = Config::apiUrl()."/wallets/{$walletId}/credit";
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
                'Content-Type' => 'application/json;charset=utf-8'
            ];

            $req_body = [
                'type' => $type,
                'memo' => $memo,
                'amount' => $amount,
                'currency' => $currency
            ];

            $request = new Request("POST", $url, $headers, json_encode($req_body));

            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success && isset($body->data)){
                return $body->data;
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

    public function withdraw(string $walletId, float $amount = 0, $currency = "XOF", string $memo=""){
        try{
            $url = Config::apiUrl()."/wallets/{$walletId}/debit";
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
                'Content-Type' => 'application/json;charset=utf-8'
            ];

            $req_body = [
                'type' => "normal",
                'memo' => $memo,
                'amount' => $amount,
                'currency' => $currency
            ];

            $request = new Request("POST", $url, $headers, json_encode($req_body));

            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success && isset($body->data)){
                return $body->data;
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
}
?>