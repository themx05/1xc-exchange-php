<?php
    require_once("./vendor/autoload.php");
    const APP_BASE = __DIR__;
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

    spl_autoload_register(function(string $className){
        $className = str_replace("\\",DIRECTORY_SEPARATOR,$className);
        require_once("./$className.php");
    });
?>