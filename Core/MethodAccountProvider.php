<?php

namespace Core;

use Models\MethodAccount;
use PDO;
use stdClass;
use Utils\Utils;

class MethodAccountProvider extends Provider{

    public function createAccount(stdClass $data): string{
        if(isset($data->type) && isset($data->details)){
            $insertion_query = "INSERT INTO MethodAccount(id, data) VALUES(?,?)";
            $insertion_stmt = $this->client->prepare($insertion_query);
            $data->id = Utils::generateHash();
            if($insertion_stmt->execute([$data->id, json_encode($data)])){
                return $data->id;
            }
        }
        return "";
    }

    public function getAccountById(string $id){
        $query = "SELECT * FROM MethodAccount WHERE id = ?";
        $select_stmt = $this->client->prepare($query);
        if($select_stmt->execute([$id])){
            $account = $select_stmt->fetch(PDO::FETCH_ASSOC);
            $parsed = new MethodAccount(json_decode($account['data']));
            return $parsed;
        }
        return null;
    }

    public function getAccountByType(string $type){
        $query = "SELECT * FROM MethodAccount WHERE JSON_EXTRACT(data,'$.type') = ?";
        $select_stmt = $this->client->prepare($query);
        if($select_stmt->execute([$type])){
            $account = $select_stmt->fetch(PDO::FETCH_ASSOC);
            $parsed = new MethodAccount(json_decode($account['data']));
            return $parsed;
        }
        return null;
    }

    public function getPerfectMoney(){
        $method = $this->getAccountByType(MethodAccount::TYPE_PERFECT);
        if($method != null){
           return $method->detailsAsPerfectMoney();
        }
        return null;
    }

    public function getFedaPay(){
        $method = $this->getAccountByType(MethodAccount::TYPE_FEDAPAY);
        if($method != null){
            return $method->detailsAsFedapay();
        }
        return null;
    }

    public function getCoinbase(){
        $method = $this->getAccountByType(MethodAccount::TYPE_COINBASE);
        if($method != null){
            return $method->detailsAsCoinbase();
        }
        return null;
    }

    public function getAccounts(): array{
        $query = "SELECT data FROM MethodAccount";
        $rows = [];
        $select_stmt = $this->client->query($query);
        if($select_stmt){
            while($raw = $select_stmt->fetch(PDO::FETCH_ASSOC)){
                array_push($rows, new MethodAccount(json_decode($raw['data'])));
            }
        }
        return [];
    }

    public function updateAccount(MethodAccount $data): bool{
        if(isset($data->type) && isset($data->details)){
            $insertion_query = "UPDATE MethodAccount SET data = ? WHERE id = ?";
            $insertion_stmt = $this->client->prepare($insertion_query);
            if($insertion_stmt->execute([json_encode($data), $data->id])){
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

?>