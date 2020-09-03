<?php

use Core\MerchantProvider;
use Core\TicketProvider;
use Core\UserProvider;
use Core\WalletProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$userRouter = new Router();
$userRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        $next();
    }else{
        $res->json(Utils::buildErrors([],['requireAuth' => true]));
    }
});

$userRouter->get("/",function(Request $req, Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    if($req->getOption("isAdmin")){
        $profiles = $userProvider->getAllProfiles();
        return $res->json(Utils::buildSuccess($profiles));
    }else{
        $profile = $userProvider->getProfileById($req->getOption('user')['id']);
        if(isset($profile)){
            return $res->json(Utils::buildSuccess($profile));
        }
    }

    $res->json(Utils::buildErrors());
});

$userRouter->patch("/credentials", function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(($req->getOption('isAdmin') === false)){
        $userProvider = new UserProvider($req->getOption('storage'));
        $user = $userProvider->getProfileById($req->getOption('user')['id']);
        if($user !== null){
            if($user->passwordHash !== $userProvider->encryptPassword($data->lastPassword)){
                return $res->json(Utils::buildErrors(['lastPassword' => 'Wrong password.']));
            }
            $done = $userProvider->updateCredentials($user, $data);
            if($done){
                return $res->json(Utils::buildSuccess());
            }
        }
    }

    $res->json(Utils::buildErrors());
});

$singleUser = new Router();

$singleUser->get("/", function(Request $req,Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    $profile = $userProvider->getProfileById($req->getParam('user'));
    if($profile !== null && ($profile->id === $req->getOption('user')['id'] || $req->getOption('isAdmin'))){
        return $res->json(Utils::buildSuccess($profile));
    }
    $res->json(Utils::buildErrors());
});

$singleUser->get("/tickets", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $ticketProvider = new TicketProvider($req->getOption('storage'));
        $tickets = $ticketProvider->getTicketsByUser($userId);
        if(isset($tickets)){
            return $res->json(Utils::buildSuccess($tickets));
        }
    }
    return $res->json(Utils::buildErrors());
});

$singleUser->get("/business", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $merchantProvider = new MerchantProvider($req->getOption('storage'));
        $profile = $merchantProvider->getBusinessProfileByUser($userId);
        if($profile !==null){
            return $res->json(Utils::buildSuccess($profile));
        }
    }
    return $res->json(Utils::buildErrors());
});

$singleUser->get("/wallet", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $walletProvider = new WalletProvider($req->getOption('storage'));
        $wallet =  $walletProvider->getMainUserWallet($userId);
        if($wallet !== null){
            return $res->json(Utils::buildSuccess($wallet));
        }
    }
    return $res->json(Utils::buildErrors());
});

$singleUser->get("/wallets", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $walletProvider = new WalletProvider($req->getOption('storage'));
        $wallets =  $walletProvider->getWalletsByUser($userId);
        return $res->json(Utils::buildSuccess($wallets));
    }
    return $res->json(Utils::buildErrors());
});

$singleUser->get("/:action", function(Request $req, Response $res){
    $action = $req->getParam('action');
    $id = $req->getParam('user');
    if($req->getOption('isAdmin')){
        $userProvider = new UserProvider($req->getOption('storage'));
        $done = false;
        if($action === 'enable'){
            $done = $userProvider->enableProfile($id);
        }
        else if($action === 'disable'){
            $done = $userProvider->disableProfile($id);
        }
        if($done){
            return $res->json(Utils::buildSuccess());
        }
    }
    $res->json(Utils::buildErrors());
});

$userRouter->router("/:user", $singleUser);

global $application;
$application->router("/users", $userRouter);
?>