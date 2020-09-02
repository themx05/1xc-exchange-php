<?php

use Core\TransactionProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$transactionRouter = new Router();

$transactionRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('isAdmin')){
        return $next();
    }
    return $res->json(['success' => false]);
});

$transactionRouter->get("/", function(Request $req, Response $res){
    $transactionProvider = new TransactionProvider($req->getOption('storage'));
    $transactions = $transactionProvider->getTransactions();
    return $res->json(buildSuccess($transactions));
});

global $application;
$application->router("/transactions", $transactionRouter);
?>