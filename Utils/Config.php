<?php
namespace Utils;

use stdClass;

class Config{
    public static ?stdClass $saved_config = null;

    static function readConfigurations(): stdClass{
        if( static::$saved_config === null || !isset(static::$saved_config->metadata)){
            static::$saved_config = json_decode(file_get_contents("/etc/1xc/services/exchange.json"));
        }
        return static::$saved_config;
    }

    static function getDefaultDatabase(){
        return static::readConfigurations()->mysql;
    }

    static function apiUrl(): string{
        return static::readConfigurations()->apiUrl;
    }

    static function metadata(): stdClass{
        return static::readConfigurations()->metadata;
    }
}

?>