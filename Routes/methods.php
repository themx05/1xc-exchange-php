<?php

use Core\BalanceProvider;
use Core\MethodProvider;
use Models\Method;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$methodRouter = new Router();

$methodRouter->get("/",function (Request $req, Response $res){
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $methods = $methodProvider->getMethods();
    if(isset($methods)){
        $res->json(Utils::buildSuccess($methods));
        return;
    }
    $res->json(Utils::buildErrors());

});


$methodRouter->get("/:method",function (Request $req, Response $res){
    $methodId = $req->getParam('method');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $method = $methodProvider->getMethodById($methodId);
    if($method !== null){
        $res->json(Utils::buildSuccess($method));
        return;
    }
    $res->json(Utils::buildErrors());
});

$methodRouter->get("/:method/balance",function (Request $req, Response $res){
    $methodId = $req->getParam('method');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $method = $methodProvider->getMethodById($methodId);
    if(isset($method)){
        $balanceProvider = new BalanceProvider($req->getOption('storage'));
        $balance = $balanceProvider->getBalance($method);
        if($balance != -1){
            return $res->json(Utils::buildSuccess($balance));
        }
        else{
            return $res->json(Utils::buildErrors());
        }
    }
    $res->json(Utils::buildErrors());
});

$methodRouter->post("/", function(Request $req, Response $res) {
    $method = $req->getOption('body');
    $methodProvider = new MethodProvider($req->getOption('storage'));

    if($req->getOption('isAdmin')){
        $hash = $methodProvider->storeMethod(json_decode(json_encode($method), true));
        if(!empty($hash)){
            return $res->json(Utils::buildSuccess($hash));
        }
    }
    $res->json(['success' => false]);
});

$methodRouter->patch("/:id",function(Request $req, Response $res){
    $mid = $req->getParam('id');
    $method = new Method(json_decode(json_encode($req->getOption('body')),true));
    $method->id = $mid;
    $client = $req->getOption('storage');
    if($req->getOption('isAdmin')){
        if($client instanceof PDO){
            $methodProvider = new MethodProvider($client);
            $client->beginTransaction();
            $done = $methodProvider->updateMethod($method);
            if($done){
                $client->commit();
                return $res->json(Utils::buildSuccess($done));
            }
            $client->rollBack();
        }
    }
    $res->json(Utils::buildErrors());
});

$methodRouter->delete("/:id",function (Request $req, Response $res){
    $method = $req->getParam('id');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $hash = $methodProvider->deleteMethod($method);
    if(!empty($hash)){
        $res->json(Utils::buildSuccess($hash));
    }
    else{
        $res->json(Utils::buildErrors());
    }
});

global $application;
$application->router("/methods",$methodRouter);
?>