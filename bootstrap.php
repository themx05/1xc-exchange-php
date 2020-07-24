<?php
    require_once("./vendor/autoload.php");
    include_once("./utils/config.php");
    require_once("./utils/emails.php");
    require_once("./utils/utils.php");
    require_once("./utils/coinbase-utils.php");

    function requireDirectory(string $directory){
        $content = scandir($directory);
        $exp = "(.*)\.php";
        foreach($content as $index => $file){
            $matches = [];
            if(preg_match("/^$exp\$/",$file,$matches) === 1){
                require_once("$directory/$file");
            }
        }
    }

    function includeDirectory(string $directory){
        $content = scandir($directory);
        $exp = "(.*)\.php";
        foreach($content as $index => $file){
            $matches = [];
            if(preg_match("/^$exp\$/",$file,$matches) === 1){
                include_once("$directory/$file");
            }
        }
    }

    requireDirectory("./stringbuilder");
    requireDirectory("./router");
    requireDirectory("./core");
?>