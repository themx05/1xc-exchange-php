<?php

use Core\MethodAccountProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$methodAccountRouter = new Router();

$methodAccountRouter->global(function(Request $request, Response $response, Closure $next){
    if($request->getOption('connected')){
        $next();
    }else{
        $response->json(Utils::buildErrors([],['requireAuth' => true]));
    }
});

$methodAccountRouter->get("/", function(Request $request, Response $response){
    $methodProvider = new MethodAccountProvider($request->getOption('storage'));
    $methods = $methodProvider->getAccounts();
    $response->json(Utils::buildSuccess($methods));
});

$methodAccountRouter->get("/:id", function(Request $request, Response $response){
    $methodProvider = new MethodAccountProvider($request->getOption('storage'));
    $method = $methodProvider->getAccountById($request->getParam('id'));
    if($method !== null){
        $response->json(Utils::buildSuccess($method));
    }
    else{
        $response->status(401)->json(Utils::buildErrors(["Identifiant de compte invalide"]));
    }
});

$methodAccountRouter->post("/", function(Request $request, Response $response){
    $methodProvider = new MethodAccountProvider($request->getOption('storage'));
    if($request->getOption('isAdmin') === true){
        $data = $request->getOption('body');
        if(isset($data->type) && isset($data->details)){
            $hash = $methodProvider->createAccount($data);
            if(!empty($hash)){
                return $response->json(Utils::buildSuccess($hash));
            }
        }
    }
    return $response->status(403)->json(Utils::buildErrors([]));
});

$methodAccountRouter->patch("/:id",function(Request $request, Response $response){
    $methodProvider = new MethodAccountProvider($request->getOption('storage'));
    $id = $request->getParam('id');
    if($request->getOption('isAdmin') === true){
        $data = $request->getOption('body');
        if(isset($data->type) && isset($data->details)){
            $done = $methodProvider->updateAccount($id,$data);
            if($done){
                return $response->json(Utils::buildSuccess($done));
            }
        }   
    }
    return $response->status(403)->json(Utils::buildErrors([]));
});

$methodAccountRouter->delete("/:id",function(Request $request, Response $response){
    $methodProvider = new MethodAccountProvider($request->getOption('storage'));
    $id = $request->getParam('id');
    if($request->getOption('isAdmin') === true){
        $data = $request->getOption('body');
        if(isset($data->type) && isset($data->details)){
            $done = $methodProvider->deleteAccount($id);
            if($done){
                return $response->json(Utils::buildSuccess($done));
            }
        }   
    }
    return $response->json(Utils::buildErrors([]));
});

global $application;
$application->router("/accounts", $methodAccountRouter);
?>