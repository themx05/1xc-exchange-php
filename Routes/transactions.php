<?php

use Core\TransactionProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$transactionRouter = new Router();

$transactionRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('isAdmin')){
        return $next();
    }
    return $res->json(Utils::buildErrors());
});

$transactionRouter->get("/", function(Request $req, Response $res){
    $transactionProvider = new TransactionProvider($req->getOption('storage'));
    $transactions = $transactionProvider->getTransactions();
    return $res->json(Utils::buildSuccess($transactions));
});

global $application;
$application->router("/transactions", $transactionRouter);
?>