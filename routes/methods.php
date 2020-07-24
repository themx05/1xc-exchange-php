<?php

use Core\MethodProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$methodRouter = new Router();

$methodRouter->get("/",function (Request $req, Response $res){
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $methods = $methodProvider->getMethods();
    if(isset($methods)){
        $res->json([
            'success' => true,
            'methods' => $methods
        ]);
        return;
    }
    $res->json(['success' => false]);

});


$methodRouter->get("/:method",function (Request $req, Response $res){
    $methodId = $req->getParam('method');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $method = $methodProvider->getMethodById($methodId);
    if(isset($method)){
        $res->json([
            'success' => true,
            'method' => $method
        ]);
        return;
    }
    $res->json(['success' => false]);
});


$methodRouter->post("/", function(Request $req, Response $res) {
    $method = $req->getOption('body');
    $methodProvider = new MethodProvider($req->getOption('storage'));

    $hash = $methodProvider->storeMethod($method);

    if(!empty($hash)){
        $res->json(['success' => true]);
    }
    else{
        $res->json(['success' => false]);
    }
});

$methodRouter->patch("/:id",function(Request $req, Response $res){
    $mid = $req->getParam('id');
    $method = $req->getOption('body');
    $client = $req->getOption('storage');
    if($req->getOption('isAdmin')){
        if($client instanceof PDO){
            $methodProvider = new MethodProvider($client);
            $client->beginTransaction();
            $deletion_done = $methodProvider->deleteMethod($mid);
            if($deletion_done){
                $hash = $methodProvider->storeMethod($method, $mid);
                if(!empty($hash)){
                    $client->commit();
                    return $res->json(['success' => true]);
                }
            }
            $client->rollBack();
        }
    }
    $res->json(['success' => false]);
});

$methodRouter->delete("/:id",function (Request $req, Response $res){
    $method = $req->getParam('id');
    $methodProvider = new MethodProvider($req->getOption('storage'));
    $hash = $methodProvider->deleteMethod($method);
    if(!empty($hash)){
        $res->json(['success' => true]);
    }
    else{
        $res->json(['success' => false]);
    }
});


global $application;
$application->router("/methods",$methodRouter);
?>