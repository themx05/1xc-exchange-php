<?php
namespace Utils;

use PDO;

class DbClient{

    static function getInstance(): PDO{
        return DbClient::prepareInstance(Config::getDefaultDatabase());
    }

    static function prepareInstance($database): PDO{
        $dsn = "mysql:host={$database['host']};dbname={$database['database']};";
        $pdo = new PDO($dsn,$database['username'],$database['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
?>