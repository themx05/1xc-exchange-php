<?php

use Core\UserProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

use \Firebase\JWT\JWT;

$signinRouter = new Router();

$signinRouter->get("/", function(Request $req, Response $res){
    if($req->getOption('connected') && !$req->getOption('isAdmin')){
        return $res->json(buildSuccess(['connected' => true]));
    }
    return $res->json(buildErrors(["Vous n'etes pas connectés"]));
});

$signinRouter->post("/",function(Request $req, Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    $systemProvider = new Core\SystemProperties($req->getOption('storage'));
    $authentication = $systemProvider->getSystemProperties()->authentication;
    $data = $req->getOption('body');
    $profile = $userProvider->getProfileByEmail($data->email);

    if(isset($profile) && $userProvider->encryptPassword($data->password) === $profile['passwordHash']){
        if($profile['verified']){
            if($profile['status'] === "active"){
                $payload = array(
                    'iss' => 'https://api.1xcrypto.net',
                    'iat' => time(),
                    'exp' => time() + 86400*3, /// 3 days
                    'uid' => $profile['id'],
                    'firstName' => $profile['firstName'],
                    'lastName' => $profile['lastName'],
                    'scope' => 'user',
                );

                $token = JWT::encode($payload, $authentication->secret);
                setcookie('token',$token,[
                    'expires' => time() + 86400*3
                ]);
                return $res->json(buildSuccess($token));
            }
            else{
                return $res->json(buildErrors(["Votre profil n'est pas actif."], ['active' => false]));
            }
        }
        else{
            return $res->json(buildErrors(["Votre profil n'est pas vérifié."],['requireVerification' => true]));
        }
    }
    $res->status(401)->json(buildErrors(["Wrong credentials"]));
});

global $application;
$application->router("/signin", $signinRouter);
?>