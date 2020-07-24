<?php

use Core\UserProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$signinRouter = new Router();

$signinRouter->get("/", function(Request $req, Response $res){
    if($req->getOption('connected') && !$req->getOption('isAdmin')){
        return $res->json(['success'=>true]);
    }
    return $res->json(['success'=> false]);
});

$signinRouter->post("/",function(Request $req, Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    $data = $req->getOption('body');
    $profile = $userProvider->getProfileByEmail($data->email);

    if(isset($profile) && $userProvider->encryptPassword($data->password) === $profile['passwordHash']){
        if($profile['verified']){
            if($profile['status'] === "active"){
                logCustomer($profile);
                return $res->json(["success"=> true]);
            }
            else{
                return $res->json(['success' => false, 'active' => false]);
            }
        }
        else{
            return $res->json(['success' => false, 'requireVerification' => true]);
        }
    }
    $res->status(401)->json([
        "success"=>false,
        "message"=>"Wrong credentials"
    ]);
});

global $application;
$application->router("/signin", $signinRouter);
?>