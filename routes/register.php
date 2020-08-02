<?php

use Core\UserProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$registrationRouter = new Router();

$registrationRouter->post("/",function (Request $req, Response $res){
    $data = $req->getOption('body');
    $msg = [
        'success' => false,
    ];

    if(!isset($data->firstName)){
        $msg['message'] = "Invalid firstname";
    }
    if(!isset($data->lastName)){
        $msg['message'] = "Invalid lastname";
    }
    if(!isset($data->country)){
        $msg['message'] = "Invalid country";
    }
    if(!isset($data->gender) || !in_array($data->gender, ['male','female'])){
        $msg['message'] = "Invalid gender";
    }
    if(!isset($data->email)){
        $msg['message'] = "Invalid email";
    }
    if(!isset($data->password)){
        $msg['message'] = "Invalid password";
    }

    if(isset($msg['message']) && !empty($msg['message'])){
        return $res->status(401)->json($msg);
    }

    $userProvider = new UserProvider($req->getOption('storage'));

    $data->lastName = ucfirst(strtolower($data->lastName));
    $data->firstName = ucfirst(strtolower($data->firstName));

    if($userProvider->getProfileByEmail($data->email) !== null){
        return $res->json(['success' => false, 'error' => 'possible_duplicate_data', 'message'=>'A user already exists with the same email address.']);
    }

    $id = $userProvider->createProfile($data);
    if(!empty($id)){
        return $res->json([
            'success'=> true,
            'message' => 'user created',
            'id' => $id,
            'requireVerification' => true
        ]);
    }
    $res->json(['success'=> false]);
});

$registrationRouter->get("/:activationCode", function(Request $req, Response $res){
    $activationCode = $req->getParam('activationCode');
    $userProvider = new UserProvider($req->getOption('storage'));
    if($userProvider->activateProfile($activationCode)){
        return $res->json(['success'=>true]);
    }
    return $res->json(['success'=>false]);
});

global $application;
$application->router("/register", $registrationRouter);
?>