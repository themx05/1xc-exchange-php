<?php

namespace Utils;

use Exception;
use PDO;

class Utils{

    static function randomString(int $length = 32): string{
        $items = 'QWERTYUIOP1234ASDFGHJKL5678ZXCVBNM90';
        $hash = "";
        $length = strlen($items);
        for($i = 0 ;$i<$length; $i++){
            $rand_pos = rand(0, $length-1);
            $hash="{$hash}{$items[$rand_pos]}";
        }
        return $hash;
    }

    static function generateHash(): string{
        return md5(static::randomString());
    }

    static function generateVerificationCode(int $length=6): string{
        return static::randomString($length);
    }

    static function getDatabaseInstance():PDO{
        try{
            $instance = DbClient::getInstance();
            return $instance;
        }
        catch(Exception $e){
            return null;
        }
    }

    static function protectString($val){
        return filter_var($val,FILTER_SANITIZE_STRING);
    }

    static function protectEmail($val){
        return filter_var($val,FILTER_SANITIZE_EMAIL);
    }

    static function fullCryptoName(string $crypto){
        $lower = strtolower($crypto);
        if($lower === 'btc'){
            return 'bitcoin';
        }
        else if ($lower === 'eth'){
            return 'ethereum';
        }
        return "";
    }

    static function buildSuccess($data=null): array{

        $ret = array(
            'success' => true
        );
    
        if(isset($data)){
            $ret['data'] = $data;
        }
    
        return $ret;
    }

    static function buildErrors(array $errors = [], array $additional = []){
        $ret = array(
            'success' => false
        );
    
        if(!empty($errors)){
            $hasNamed = true;
            foreach($errors as $key => $value){
                if(! preg_match("/^[a-zA-Z]{1}(.*)\$/", $key)){
                    $hasNamed = false;
                }
            }
            if(!$hasNamed){
                $ret['errors'] = ['default' => $errors[0]];
            }else{
                $ret['errors'] = $errors;
            }
        }
    
        if(!empty($additional)){
            $ret = array_merge($ret, $additional);
        }
        
        return $ret;
    }

    static function extractBearerToken(string $str): string{
        $matches = [];
        preg_match("/^(bearer)\s+(?<token>.*)$/i/", $str, $matches);
        if(isset($matches['token'])){
            return $matches['token'];
        }
        return null;
    }
}