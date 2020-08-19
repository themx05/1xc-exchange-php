<?php

function randomString(int $length = 32): string{
    $items = 'QWERTYUIOP1234ASDFGHJKL5678ZXCVBNM90';
    $hash = "";
    $length = strlen($items);
    for($i = 0 ;$i<$length; $i++){
        $rand_pos = rand(0, $length-1);
        $hash="{$hash}{$items[$rand_pos]}";
    }
    return $hash;
}

function generateHash(): string{
    return md5(randomString());
}

function generateVerificationCode(int $length=6): string{
    return randomString($length);
}

function getDatabaseInstance():PDO{
    /*$system = new SystemConfig();
    if($system->isConfigured()){
        return DbClient::prepareInstance($system->getDatabaseConfiguration());
    }*/
    return DbClient::getInstance();
}

function logCustomer(array $user){
    $_SESSION['id'] = $user['id'];
    $_SESSION['type'] = 'user';
    $_SESSION['firstName'] = $user['firstName'];
    $_SESSION['lastName'] = $user['lastName'];
}

function logAdmin(array $user){
    $_SESSION['id'] = $user['id'];
    $_SESSION['type'] = 'admin';
    $_SESSION['firstName'] = $user['firstName'];
    $_SESSION['lastName'] = $user['lastName'];
}

function isConnected(){
    return isset($_SESSION['id']) && (!empty($_SESSION['id'])) && isset($_SESSION['type']) && (!empty($_SESSION['type']));
}

function getUser(){
    return [
        'id' => $_SESSION['id'],
        'type' => $_SESSION['type'],
        'firstName' => $_SESSION['firstName'],
        'lastName' => $_SESSION['lastName']
    ];
}

function logUserOut(){
    session_unset();
    session_destroy();
}

function isUserAnAdmin(){
    return isConnected() && getUser()['type'] === "admin";
}

function protectString($val){
    return filter_var($val,FILTER_SANITIZE_STRING);
}

function isSystemConfigured(){
    global $client;
    $query = "SELECT count(id) as count FROM Configuration";
    $stmt = $client->query($query);
    if($stmt){
        $count =(int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if($count > 0){
            return true;
        }
    }
    return false;
}

function getStoredConfiguration(){
    global $client;
    $query = "SELECT attrs FROM Configuration";
    $stmt = $client->query($query);
    if($stmt){
        $configuration = json_decode($stmt->fetch(PDO::FETCH_ASSOC), true);
        return $configuration;
    }
    return [];
}

function fullCryptoName(string $crypto){
    $lower = strtolower($crypto);
    if($lower === 'btc'){
        return 'bitcoin';
    }
    else if ($lower === 'eth'){
        return 'ethereum';
    }
    return "";
}

function buildSuccess($data): array{

    $ret = array(
        'success' => true
    );

    if(isset($data)){
        $ret['data'] = $data;
    }

    return $ret;
}

function buildErrors(array $errors = [], array $additional = []){
    $ret = array(
        'success' => false,
    );

    if(!empty($errors)){
        $ret['errors'] = $errors;
    }

    if(!empty($additional)){
        $ret = array_merge($ret, $additional);
    }
    
    return $ret;
}

