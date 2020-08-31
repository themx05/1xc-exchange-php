<?php

use Core\UserProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$registrationRouter = new Router();

$registrationRouter->post("/",function (Request $req, Response $res){
    $data = $req->getOption('body');
    $msg = [];

    if(!isset($data->firstName)){
        $msg['firstName'] = "Invalid firstname";
    }
    if(!isset($data->lastName)){
        $msg['lastName'] = "Invalid lastname";
    }
    if(!isset($data->country)){
        $msg['country'] = "Invalid country";
    }
    if(!isset($data->gender) || !in_array($data->gender, ['male','female'])){
        $msg['gender'] = "Invalid gender";
    }
    if(!isset($data->email)){
        $msg['email'] = "Invalid email";
    }
    if(!isset($data->password)){
        $msg['password'] = "Invalid password";
    }

    if(count($msg) > 0){
        return $res->status(401)->json(buildErrors($msg));
    }

    $userProvider = new UserProvider($req->getOption('storage'));

    $data->lastName = ucfirst(strtolower($data->lastName));
    $data->firstName = ucfirst(strtolower($data->firstName));

    if($userProvider->getProfileByEmail($data->email) !== null){
        return $res->json(buildErrors(['email' => 'A user already exists with the same email address.']));
    }

    $id = $userProvider->createProfile($data);
    if(!empty($id)){
        return $res->json(buildSuccess([
            'message' => 'user created',
            'id' => $id,
            'requireVerification' => true
        ]));
    }
    $res->json(buildErrors());
});

$registrationRouter->get("/:activationCode", function(Request $req, Response $res){
    $activationCode = $req->getParam('activationCode');
    $userProvider = new UserProvider($req->getOption('storage'));
    if($userProvider->activateProfile($activationCode)){
        return $res->json(buildSuccess(true));
    }
    return $res->json(buildErrors());
});

global $application;
$application->router("/register", $registrationRouter);
?>