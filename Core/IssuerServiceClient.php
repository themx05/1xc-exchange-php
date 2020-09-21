<?php

namespace Core;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\BusinessProfile;
use PDO;
use stdClass;
use Utils\Config;
use Utils\Utils;

class IssuerServiceClient extends ServiceClient{

    public function decode(string $token): stdClass{
        try{
            $url = Config::apiUrl()."/issuer/decode";
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
            ]; 
            $req_body = [
                'token' => $token
            ];

            $request = new Request("POST", $url, $headers, json_encode($req_body));

            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success){
                $content = $body->data;
                return $content;
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

    public function sign(stdClass $data): string{
        try{
            $url = Config::apiUrl()."/issuer/sign";
            $meta = Config::metadata();
            $headers = [
                'Service-Name' => $meta->name,
                'Service-Signature' => $meta->signature,
            ];

            $request = new Request("POST", $url, $headers, json_encode($data));

            $guzzle = new Client();
            $response = $guzzle->send($request);
            $body = json_decode($response->getBody()->__toString());
            if($body->success){
                return $body->data;
            }
            return "";
        }
        catch(Exception $e){
            if($this->logger){
                $this->logger->error($e->getMessage());
            }
            return "";
        }
    }
}
?>