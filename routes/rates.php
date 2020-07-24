<?php

use Routing\Request;
use Routing\Response;
use Routing\Router;

$rateRouter = new Router();

$rateRouter->get("/:source/:dest",function(Request $req, Response $res){
    $converter = new ConversionProvider();
    if($req->getParam("source") === $req->getParam("dest")){
        $response = [
            "source" => $req->getParam('source'),
            "dest" => $req->getParam('dest'),
            "rate" => 1,
            "amount" => 1,
            'converted' => 1 
        ];
        return $res->json(['success' => true, 'data' => $response]);
    }
    $data = $converter->convert([
        'source' => $req->getParam('source'),
        'dest' => $req->getParam("dest"),
        'amount' =>  1
    ]);
    
    if($data !== -1){
        if($data['rate'] > 0){
            return $res->json([
                'success' => true,
                'data' => $data
            ]);
        }
    }
    $res->json([
        'success' => false
    ]);
});


global $application;
$application->router("/rates", $rateRouter);
?>