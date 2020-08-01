<?php

use Routing\Request;
use Routing\Response;
use Routing\Router;

$fileRouter = new Router();

$fileRouter->get("/files/:file", function(Request $req, Response $res){
    $filename = $req->getParam("file");
    $homeDir= $req->getOption('home');
    $uploadDir = "$homeDir/uploads";
    $filePath = "$uploadDir/$filename";
    if(file_exists($filePath) && is_file($filePath)){
        $res->file($filePath);
    }
    else{
        $res->status(404)->text("file not found");
    }
});

$fileRouter->get("/icons/:file", function(Request $req, Response $res){
    $filename = $req->getParam("file");
    $homeDir= $req->getOption('home');
    $uploadDir = "$homeDir/uploads";
    $filePath = "$uploadDir/$filename";
    if(file_exists($filePath) && is_file($filePath)){
        $res->file($filePath);
    }
    else{
        $res->status(404)->text("file not found");
    }
});

global $application;
$application->router("/",$fileRouter);
?>