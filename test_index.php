<?php
require_once('./bootstrap.php');

use Models\Money;
use Routing\App;
use Routing\BodyParser;
use Routing\Request;
use Routing\Response;

$_SERVER['REQUEST_URI'] = "/".$_GET['route'];

$application = new App();

$application->global(BodyParser::json());

$application->global(function(Request $req, Response $res){
    $rawMoney = [
        'currency' => "XOF",
        'amount' => '2000'
    ];

    $money = new Money(json_decode(json_encode($rawMoney)));
    var_dump($rawMoney);
    var_dump($money);
});

$application->handle();
?>