<?php

use Core\FixedRatesProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$rateRouter = new Router();

/**
 * Added Manually Fixed Rates support to prevent market losses. 
 */

$rateRouter->get("/:source/:dest",function(Request $req, Response $res){

    $fixedProvider = new FixedRatesProvider($req->getOption('storage'));

    $source = $req->getParam('source');
    $dest = $req->getParam('dest');

    if($source === $dest){
        $response = [
            "source" => $source,
            "dest" => $dest,
            "rate" => 1,
            "amount" => 1,
            'converted' => 1 
        ];
        return $res->json(['success' => true, 'data' => $response]);
    }

    $fixedRate = $fixedProvider->getRateData($source, $dest);
    if($fixedRate !== null){
        $rate  = $fixedRate['to']['amount'] / $fixedRate['from']['amount'];
        $response = [
            "source" => $source,
            "dest" => $dest,
            "rate" => $rate,
            "amount" => 1,
            'converted' => 1 * $rate
        ];
        return $res->json(buildSuccess($response));
    }

    $converter = new ConversionProvider();
    $data = $converter->convert([
        'source' => $source,
        'dest' => $dest,
        'amount' =>  1
    ]);
    
    if($data !== -1){
        if($data['rate'] > 0){
            return $res->json(buildSuccess($data));
        }
    }
    return $res->json(buildErrors());
});

global $application;
$application->router("/rates", $rateRouter);
?>