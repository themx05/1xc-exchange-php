<?php

namespace Core;

use Models\Method;
use PDO;
use stdClass;
use Utils\Utils;

class MethodProvider extends Provider{

    public function getMethods(): array{
        $query = "SELECT * FROM SupportedMethods";
        $stmt = $this->client->query($query);
        $copy = [];
        if($stmt){
            $method = null;
            while($method = $stmt->fetch(PDO::FETCH_ASSOC)){
                array_push($copy, new Method(json_decode($method['data'])));
            }
        }
        return $copy;
    }

    public function getMethodById(string $id){
        $query = "SELECT * FROM SupportedMethods WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id])){
            $methods = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Method(json_decode($methods['data']));
        }
        return null;
    }

    public function storeMethod(stdClass $method){
        $query = "INSERT INTO SupportedMethods (id,data) VALUES(?,?)";
        $stmt = $this->client->prepare($query);
        if(!isset($method['id'])){
            $method['id'] = Utils::generateHash();
        }
        $stmt->execute([$method['id'], json_encode($method)]);
    }

    public function updateMethod(Method $method){
        $query = "UPDATE SupportedMethods SET data = ?  WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if(!isset($method->id)){
            return false;
        }
        if($stmt->execute([json_encode($method),$method->id])){
            return true;
        }
        return false;
    }
    
    public function deleteMethod(string $id){
        $query = "DELETE FROM SupportedMethods WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id])){
            return true;
        }else{
            return false;
        }
    }
    
}

?>