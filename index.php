<?php
require_once('./bootstrap.php');

use Core\IssuerServiceClient;
use Core\Logger;
use Core\SystemAdminProvider;
use Events\Publisher;
use Models\AdminAuth;
use Models\ServiceAuth;
use Models\UserAuth;
use Routing\App;
use Routing\BodyParser;
use Routing\CorsConfiguration;
use Routing\Request;
use Routing\Response;
use Utils\Config;
use Utils\Utils;

define("CB_URL", "https://api.coinbase.com");

$_SERVER['REQUEST_URI'] = "/".$_GET['route'];

session_start();

$client = Utils::getDatabaseInstance();
$redisClient = new Predis\Client();

$logger = new Logger($redisClient, Config::redis()->loggingChannel);

$metadatas = Config::metadata();
$key = "{$metadatas->name}.metadata";

$redisClient->set($key, json_encode($metadatas));

///Setup Event Publisher;

$eventPublisher = new Publisher($redisClient, Config::redis()->eventChannel);

$application = new App();

$cors = new CorsConfiguration();

$cors->whiteListBasicMethods();
$cors->whiteListMethods('GET','POST','PUT','PATCH','DELETE');
$cors->whiteListOrigin("localhost", "http://localhost", "http://localhost:3000", "https://1xcrypto.net", "https://1xcrypto.net");
$cors->whiteListheaders("Content-Type", "Authorization");

// Handle CORS Requests
$application->global($cors->createHandler());
// JSON content parsing middleware.
$application->global(BodyParser::json());

$application->setOption('storage', $client);
$application->setOption('redis', $redisClient);

$application->global(function(Request $req, Response $res, Closure $next){
    global $redisClient;
    //Handle Service Authentication
    $name = isset($req->headers['service-name']) ? $req->headers['service-name'] : "";
    $signature = isset($req->headers['service-signature']) ? $req->headers['service-signature'] : "";

    if(isset($name) && isset($signature)){
        $key = "{$name}.metadata";
        $meta = $redisClient->get($key);
        if(isset($meta)){
            $rawMeta = json_decode($meta);
            $decodedMeta = new ServiceAuth();
            $decodedMeta->name = $rawMeta->name;
            $decodedMeta->signature = $rawMeta->signature;
            if($rawMeta->host !== null){
                $decodedMeta->host = $rawMeta->host;
            }
            if(isset($rawMeta->port)){
                $decodedMeta->port = $rawMeta->port;
            }

            if($decodedMeta->signature === $signature){
                $req->setOption('peer', $decodedMeta);
                $req->setOption('peerType', 'service');
                $req->setOption('connected', true);
            }
        }
    }

    $next();
});

$application->global(function(Request& $req, Response $res, Closure $next){
    if($req->getOption('peer') !== null){
        return $next();
    }    
    $authorization = $req->headers['authorization'];
    if(isset($authorization)){
        $token = Utils::extractBearerToken($authorization);
        if(isset($token)){
            $issuer = new IssuerServiceClient();
            $content = $issuer->decode($token);
            if($content !== null){
                if($content->type === "admin"){
                    $admin = new AdminAuth();
                    $admin->userId = $content->userId;
                    $admin->firstName = $content->firstName;
                    $admin->lastName = $content->lastName;
                    $admin->roles = [];
                    $adminClient = new SystemAdminProvider();
                    $admin->roles = $adminClient->getAdminRoles($content->userId);
                    $req->setOption('peer', $admin);
                    $req->setOption('peerType', "admin");
                    $req->setOption('connected', true);
                    return $next();
                }
                else if($content->type === "user"){
                    $user = new UserAuth();
                    $user->userId = $content->userId;
                    $user->firstName = $content->firstName;
                    $user->lastName = $content->lastName;
                    $req->setOption('peer', $user);
                    $req->setOption('peerType', "user");
                    $req->setOption('connected', true);
                    return $next();
                }
            }
        }
    }

    return $res->status(403)->json(Utils::buildErrors());
});

includeDirectory("./Routes");

$application->global(function (Request $req, Response $res){
    $res->json(['success' => false]);
});

$application->handle();

?>