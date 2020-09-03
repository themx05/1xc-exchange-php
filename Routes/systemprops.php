<?php

use Core\SystemProperties;
use Models\SystemProps;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$systemPropsRouter = new Router();

$systemPropsRouter->get("/",function(Request $req, Response $res){
    $provider = new SystemProperties($req->getOption('storage'));
    $props = $provider->getSystemProperties();
    
    if($props !== null){
        return $res->json(Utils::buildSuccess($props));
    }
    return $res->json(Utils::buildErrors());
});

$systemPropsRouter->patch("/",function(Request $req, Response $res){
    $provider = new SystemProperties($req->getOption('storage'));
    $data = $req->getOption('body');
    $newProps = new SystemProps($data);
    $props = $provider->updateSystemProperties($newProps);
    
    if($props){
        return $res->json(Utils::buildSuccess(true));
    }
    return $res->json(Utils::buildErrors());
});

global $application;
$application->router("/systemprops", $systemPropsRouter);
?>