<?php
require_once('./bootstrap.php');

use Models\LoadFromStd;
use Models\Money;
use Routing\App;
use Routing\BodyParser;
use Routing\Request;
use Routing\Response;
use Utils\Utils;

class MySample{
    use LoadFromStd;
    public string $id = "";
    public Money $balance;
}

$application = new App();

$application->global(function(Request $req, Response $res){
    echo "Check if parsing from stdClass works";

    $rawMySample = [
        'id' => Utils::randomString(64),
        'balance' => [
            'currency' => "XOF",
            'amount' => '2000'
        ]
    ];

    var_dump($rawMySample);
    $sample = new MySample(json_decode(json_encode($rawMySample)));
    var_dump($sample);
});

$application->handle();
?>