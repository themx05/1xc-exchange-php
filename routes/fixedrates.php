<?php

/**
 * Access currently disabled for this route.
 */
use Core\FixedRatesProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$fixedRouter = new Router();

$fixedRouter->global(function(Request $req, Response $res, Closure $next){
    /**
     * Disable access
     */
    return $res->json(buildErrors());
    
    if($req->getOption('isAdmin')){
        return $next();
    }
    return $res->json(buildErrors([],['requireAuth' => true]));
});

$fixedRouter->get("/", function(Request $req, Response $res){
    $fixedProvider = new FixedRatesProvider($req->getOption('storage'));
    $rates = $fixedProvider->getRates();
    return $res->json(buildSuccess($rates));
});

$fixedRouter->post("/", function(Request $req, Response $res){
    $body = $req->getOption('body');
    if(
        isset($body->from) 
        && isset($body->from->amount) 
        && isset($body->from->currency) 
        && isset($body->to)
        && isset($body->to->currency)
        && isset($body->to->amount)
        && $body->from->amount > 0
        && $body->to->amount > 0
    ){
        $fixedProvider = new FixedRatesProvider($req->getOption('storage'));
        $rateId = $fixedProvider->fixRate(
            $body->from->currency,
            floatval($body->from->amount),
            $body->to->currency,
            floatval($body->to->amount)
        );

        if(!empty($rateId)){
            return $res->json(buildSuccess($rateId));
        }
    }
    return $res->json(buildErrors(['Propriété manquante']));
});

$fixedRouter->patch("/:id", function(Request $req, Response $res){
    $body = $req->getOption('body');
    $rateId = $req->getParam("id");
    if(
        isset($rateId)
        && isset($body->fromAmount) 
        && isset($body->toAmount) 
        && $body->fromAmount > 0
        && $body->toAmount > 0
    ){
        $fixedProvider = new FixedRatesProvider($req->getOption('storage'));
        $done = $fixedProvider->updateFixedRate(
            $rateId,
            floatval($body->fromAmount),
            floatval($body->toAmount)
        );

        if($done){
            return $res->json(buildSuccess($rateId));
        }
    }
    return $res->json(buildErrors(['Montant invalide']));
});

$fixedRouter->delete("/:id", function(Request $req, Response $res){
    $rateId = $req->getParam("id");
    if(
        isset($rateId)
    ){
        $fixedProvider = new FixedRatesProvider($req->getOption('storage'));
        $done = $fixedProvider->removeFixedRate($rateId);
        if($done){
            return $res->json(buildSuccess($done));
        }
    }
    return $res->json(buildErrors(['Propriété invalide.']));
});

global $application;
$application->router("/fixed", $fixedRouter);
?>