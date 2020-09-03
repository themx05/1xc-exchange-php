<?php
    
namespace Utils;

$phase = "staging";
$productionDatabase = [
    'host'=>'localhost',
    'port'=>3306,
    'username'=>'xcrypton_bot',
    'password'=>'3Zg5hTGKeM2P',
    'database'=>'xcrypton_1xcv2'
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

class Config{
    
    public static function getDefaultDatabase(){
        global $developmentDatabase, $productionDatabase, $phase;
        if($phase === "staging"){
            return $productionDatabase;
        }
        
        return $developmentDatabase;
    }
    public static function getDefaultEmailConfig(){
        global $developmentDatabase, $productionDatabase, $phase;
        if($phase === "staging"){
            return $productionDatabase;
        }
        return $developmentDatabase;
    }
}
    
    
?>