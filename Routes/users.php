<?php

use Core\MerchantProvider;
use Core\TicketProvider;
use Core\UserProvider;
use Providers\WalletProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$userRouter = new Router();
$userRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        $next();
    }else{
        $res->json([
            'success' => false,
            'requireAuth' => true,
            'user' => getUser()
        ]);
    }
});

$userRouter->get("/",function(Request $req, Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    if($req->getOption("isAdmin")){
        $profiles = $userProvider->getAllProfiles();
        if(isset($profiles)){
            return $res->json([
                'success' => true,
                'customers' => $profiles
            ]);
        }
    }else{
        $profile = $userProvider->getProfileById($req->getOption('user')['id']);
        if(isset($profile)){
            return $res->json([
                'success' => true,
                'user' => $profile
            ]);
        }
    }

    $res->json([
        'success' => false
    ]);
});

$userRouter->patch("/credentials", function(Request $req, Response $res){
    $data = $req->getOption('body');
    if(($req->getOption('isAdmin') === false)){
        $userProvider = new UserProvider($req->getOption('storage'));
        $done = $userProvider->updateCredentials($req->getOption('user')['id'], $data);
        if($done){
            return $res->json([
                'success' => true
            ]);
        }
    }

    $res->json([
        'success' => false
    ]);
});

$singleUser = new Router();

$singleUser->get("/", function(Request $req,Response $res){
    $userProvider = new UserProvider($req->getOption('storage'));
    $profile = $userProvider->getProfileById($req->getParam('user'));

    if(isset($profile) && ($profile['id'] === $req->getOption('user')['id'] || $req->getOption('isAdmin'))){
        return $res->json([
            'success' => true,
            'user' => $profile
        ]);
    }

    $res->json([
        'success' => false
    ]);
});

$singleUser->get("/tickets", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $ticketProvider = new TicketProvider($req->getOption('storage'));
        $tickets = $ticketProvider->getTicketsByUser($userId);
        if(isset($tickets)){
            return $res->json([
                'success' => true,
                'tickets' => $tickets
            ]);
        }
    }
    return $res->json(['success' => false]);
});

$singleUser->get("/business", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $merchantProvider = new MerchantProvider($req->getOption('storage'));
        $profile = $merchantProvider->getBusinessProfileByUser($userId);
        if(isset($profile)){
            return $res->json(['success' => true, 'profile' => $profile]);
        }
    }
    return $res->json(['success' => false]);
});

$singleUser->get("/wallet", function(Request $req, Response $res){
    $userId = $req->getParam("user");
    if($req->getOption('user')['id'] === $userId || $req->getOption('isAdmin')){
        $walletProvider = new WalletProvider($req->getOption('storage'));
        $wallet =  $walletProvider->getWalletByUser($userId);
        if(isset($wallet)){
            return $res->json(['success' => true, 'wallet' => $wallet]);
        }
    }
    return $res->json(['success' => false]);
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
            return $res->json(buildSuccess());
        }
    }
    $res->json(buildErrors());
});

$userRouter->router("/:user", $singleUser);

global $application;
$application->router("/users", $userRouter);
?>