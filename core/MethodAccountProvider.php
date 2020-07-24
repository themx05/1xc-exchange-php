<?php

namespace Core{

use PDO;
    use stdClass;

class MethodAccountProvider{
    public $client;

    public const COINBASE = "coinbase";
    public const FEDAPAY = "fedapay";
    public const PERFECT_MONEY = "perfectmoney";

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createAccount(stdClass $data): string{
        if(isset($data->type) && isset($data->details)){
            $id = generateHash();
            $insertion_query = "INSERT INTO MethodAccount(id, type, details) VALUES(?,?,?)";
            $insertion_stmt = $this->client->prepare($insertion_query);

            if($insertion_stmt->execute([generateHash(),$data->type, json_encode($data->details)])){
                return $id;
            }
        }
        return "";
    }

    public function getAccountById(string $id): array{
        $query = "SELECT * FROM MethodAccount WHERE id = ?";
        $select_stmt = $this->client->prepare($query);
        if($select_stmt->execute([$id])){
            $account = $select_stmt->fetch(PDO::FETCH_ASSOC);
            $account['details'] = json_decode($account['details'], true);

            return $account;
        }
        return null;
    }

    public function getAccountByType(string $type): array{
        $query = "SELECT * FROM MethodAccount WHERE type = ?";
        $select_stmt = $this->client->prepare($query);
        if($select_stmt->execute([$type])){
            $account = $select_stmt->fetch(PDO::FETCH_ASSOC);
            $account['details'] = json_decode($account['details'], true);
            return $account;
        }
        return null;
    }

    public function getPerfectMoney(): array{
        return $this->getAccountByType(MethodAccountProvider::PERFECT_MONEY);
    }

    public function getFedaPay(): array{
        return $this->getAccountByType(MethodAccountProvider::FEDAPAY);
    }

    public function getCoinbase(): array{
        return $this->getAccountByType(MethodAccountProvider::COINBASE);
    }

    public function getAccounts(): array{
        $query = "SELECT * FROM MethodAccount";
        $select_stmt = $this->client->query($query);
        if($select_stmt){
            $rows = [];
            $raws = $select_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($raws as $value){
                $value['details'] = json_decode($value['details'], true);
                array_push($rows, $value);
            }
            return $rows;
        }
        return null;
    }

    public function updateAccount(string $id, stdClass $data): bool{
        if(isset($data->type) && isset($data->details)){
            $insertion_query = "UPDATE MethodAccount SET type = ?, details = ? WHERE id = ?";
            $insertion_stmt = $this->client->prepare($insertion_query);
            if($insertion_stmt->execute([$data->type,json_encode($data->details), $id])){
                return true;
            }
        }
        return false;
    }

    public function deleteAccount(string $accountId):bool{
        $deletion_query = "DELETE FROM MethodAccount WHERE id = ?";
        $deletion_stmt = $this->client->prepare($deletion_query);
        if($deletion_stmt->execute([$accountId])){
            return true;
        }
        return false;
    }
}
}
?>