<?php
namespace Utils;

use PDO;
use stdClass;

class DbClient{

    static function getInstance(): PDO{
        return DbClient::prepareInstance(Config::getDefaultDatabase());
    }

    static function prepareInstance( stdClass $database): PDO{
        $dsn = "mysql:host={$database->host};dbname={$database->database};";
        $pdo = new PDO($dsn,$database->user,$database->password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
?>