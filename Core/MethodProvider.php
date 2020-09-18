<?php

namespace Core;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\Method;
use Utils\Config;

class MethodProvider extends ServiceClient{

    public function getMethodById(string $id): ?Method{
        try{
            $url = Config::apiUrl()."/system/methods/".$id;
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
                $profile = new Method($body->data);
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
}

?>