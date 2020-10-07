<?php
namespace Core;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Models\ExchangeRate;
use Utils\Config;

class ConversionService{

    function convert(string $from, string $to): ?ExchangeRate{       
        global $logger;      
        try{ 
            $url = Config::apiUrl()."/rates/".$from."/".$to;
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
                $rate = new ExchangeRate();
                $rate->load($body->data);
                return $rate;
            }
            return null;
        }
        catch(Exception $e){
            $logger->error($e->getMessage());
            return null;
        }
        if(isset($account)){
            return $account;
        }
    }
}

?>