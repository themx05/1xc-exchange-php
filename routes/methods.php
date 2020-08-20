<?php

use Core\BalanceProvider;
use Core\MethodProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$methodRouter = new Router();

$methodRouter->get("/",function (Request $req, Response $res){
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $methods = $methodProvider->getMethods();
    if(isset($methods)){
        $res->json(buildSuccess($methods));
        return;
    }
    $res->json(buildErrors());

});


$methodRouter->get("/:method",function (Request $req, Response $res){
    $methodId = $req->getParam('method');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $method = $methodProvider->getMethodById($methodId);
    if(isset($method)){
        $res->json(buildSuccess($method));
        return;
    }
    $res->json(buildErrors());
});

$methodRouter->get("/:method/balance",function (Request $req, Response $res){
    $methodId = $req->getParam('method');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $method = $methodProvider->getMethodById($methodId);
    if(isset($method)){
        $balanceProvider = new BalanceProvider($req->getOption('storage'));
        $balance = $balanceProvider->getBalance($method);
        if($balance != -1){
            return $res->json(buildSuccess($balance));
        }
        else{
            return $res->json(buildErrors());
        }
    }
    $res->json(buildErrors());
});

$methodRouter->post("/", function(Request $req, Response $res) {
    $method = $req->getOption('body');
    $methodProvider = new MethodProvider($req->getOption('storage'));

    if($req->getOption('isAdmin')){
        $hash = $methodProvider->storeMethod(json_decode(json_encode($method), true));
        if(!empty($hash)){
            return $res->json(buildSuccess($hash));
        }
    }
    $res->json(['success' => false]);
});

$methodRouter->patch("/:id",function(Request $req, Response $res){
    $mid = $req->getParam('id');
    $method = json_decode(json_encode($req->getOption('body')),true);
    $method['id'] = $mid;
    $client = $req->getOption('storage');
    if($req->getOption('isAdmin')){
        if($client instanceof PDO){
            $methodProvider = new MethodProvider($client);
            $client->beginTransaction();
            $done = $methodProvider->updateMethod($method);
            if($done){
                $client->commit();
                return $res->json(buildSuccess($done));
            }
            $client->rollBack();
        }
    }
    $res->json(buildErrors());
});

$methodRouter->delete("/:id",function (Request $req, Response $res){
    $method = $req->getParam('id');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $hash = $methodProvider->deleteMethod($method);
    if(!empty($hash)){
        $res->json(buildSuccess($hash));
    }
    else{
        $res->json(buildErrors());
    }
});

global $application;
$application->router("/methods",$methodRouter);
?>