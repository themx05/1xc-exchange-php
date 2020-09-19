<?php

use Core\TransactionProvider;
use Models\AdminAuth;
use Models\ServiceAuth;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$transactionRouter = new Router();

$transactionRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    return $res->json(Utils::buildErrors());
});

$transactionRouter->get("/", function(Request $req, Response $res){
    $peer = $req->getOption('peer');
    if($peer !== null && ( ($peer instanceof AdminAuth && $peer->hasRole("ticket:transaction:read")) || $peer instanceof ServiceAuth ) ){
        $transactionProvider = new TransactionProvider($req->getOption('storage'));
        $transactions = $transactionProvider->getTransactions();
        return $res->json(Utils::buildSuccess($transactions));
    }
    else{
        return $res->json(Utils::buildErrors());
    }
});

global $application;
$application->router("/transactions", $transactionRouter);
?>