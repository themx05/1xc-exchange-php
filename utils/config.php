<?php
    
    $phase = "production";

    $productionDatabase = [
        'host'=>'localhost',
        'port'=>3306,
        'username'=>'xcrypto_bot',
        'password'=>'M_Ywt~ua|:0kv1MJI^lu);6Ax#QNp!O)',
        'database'=>'1xc_v1'
    ];

    $developmentDatabase = [
        'host'=>'localhost',
        'port'=>3306,
        'username'=>'1xcrypto_bot',
        'password'=>'1xcrypto@engine2020',
        'database'=>'1xcrypto'
    ];

    $email = [
        'user'=>"admin@1xcrypto.net",
        'password' => 'bBWbw(UU5J#g',
        'port' => '587'
    ];

    define("FIXER_API_KEY",'9a6ef038aaace3eb009165d70392a10c');
    define("FIXER_URL",'http://data.fixer.io/api/latest?');
    define("FR_CONV_API_KEY","553633de7f70634c6ad5");
    define("FR_CONV_URL","https://free.currconv.com/api/v7/convert?");


    function getDefaultDatabase(){
        global $developmentDatabase, $productionDatabase, $phase;
        if($phase === "production"){
            return $productionDatabase;
        }
        
        return $developmentDatabase;
    }

    function getDefaultEmailConfig(){
        global $developmentDatabase, $productionDatabase, $phase;
        if($phase === "production"){
            return $productionDatabase;
        }
        return $developmentDatabase;
    }
    
    class DbClient{

        static function getInstance(): PDO{
            return DbClient::prepareInstance(getDefaultDatabase());
        }

        static function prepareInstance($database): PDO{
            $dsn = "mysql:host={$database['host']};dbname={$database['database']};";
            $pdo = new PDO($dsn,$database['username'],$database['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            return $pdo;
        }
    }
?>