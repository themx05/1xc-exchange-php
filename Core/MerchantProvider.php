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

class MerchantProvider extends ServiceClient{

    public function getProfileById(string $id): ?BusinessProfile{
        try{
            $url = Config::apiUrl()."/business/".$id;
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
                $profile = new BusinessProfile();
                $profile->load($body->data);
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