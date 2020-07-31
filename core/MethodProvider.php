<?php

namespace Core{

    use PDO;
class MethodProvider{
        public $client;

        public function __construct(PDO $client){
            $this->client = $client;
        }

        public function getMethods(): array{
            $query = "SELECT * FROM SupportedMethods";
            $stmt = $this->client->query($query);
            if($stmt){
                $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $copy = [];
                foreach($methods as $method){
                    $parsed = $method;
                    $parsed['data'] = json_decode($method['data'], true);
                    array_push($copy, $parsed['data']);
                }
                return $copy;
            }
            return null;
        }

        public function getMethodById(string $id): array{
            $query = "SELECT * FROM SupportedMethods WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$id])){
                $methods = $stmt->fetch(PDO::FETCH_ASSOC);
                return json_decode($methods['data'],true);
            }
            return null;
        }

        public function storeMethod(array $method){
            $query = "INSERT INTO SupportedMethods (id,data) VALUES(?,?)";
            $stmt = $this->client->prepare($query);
            if(!isset($method['id'])){
                $method['id'] = generateHash();
            }
            $stmt->execute([$method['id'], json_encode($method)]);
        }

        public function updateMethod(array $method){
            $query = "UPDATE SupportedMethods SET data = ?  WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if(!isset($method['id'])){
                return false;
            }
            if($stmt->execute([json_encode($method),$method['id']])){
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
}

?>