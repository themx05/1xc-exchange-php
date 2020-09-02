<?php

use Core\SystemProperties;
use Models\SystemProps;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$systemPropsRouter = new Router();

$systemPropsRouter->get("/",function(Request $req, Response $res){
    $provider = new SystemProperties($req->getOption('storage'));
    $props = $provider->getSystemProperties();
    
    if($props !== null){
        return $res->json(buildSuccess($props));
    }
    return $res->json(buildErrors());
});

$systemPropsRouter->patch("/",function(Request $req, Response $res){
    $provider = new SystemProperties($req->getOption('storage'));
    $data = $req->getOption('body');
    $newProps = new SystemProps($data);
    $props = $provider->updateSystemProperties($newProps);
    
    if($props){
        return $res->json(buildSuccess(true));
    }
    return $res->json(buildErrors());
});

global $application;
$application->router("/systemprops", $systemPropsRouter);
?>