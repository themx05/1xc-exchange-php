<?php

namespace Core{

    use PDO;
    use stdClass;

class MethodProvider{
        public $client;

        public function __construct(PDO $client){
            $this->client = $client;
        }

        public function getMethods(): array{
            $query = "SELECT * FROM Method";
            $stmt = $this->client->query($query);
            if($stmt){
                $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $copy = [];
                foreach($methods as $method){
                    $parsed = $method;
                    $parsed['details'] = json_decode($method['details']);
                    array_push($copy, $parsed);
                }
                return $copy;
            }
            return null;
        }

        public function getMethodById(string $id): array{
            $query = "SELECT * FROM Method WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$id])){
                $methods = $stmt->fetch(PDO::FETCH_ASSOC);
                $methods['details'] = json_decode($methods['details']);
                return $methods;
            }
            return null;
        }

        private function storeMobileMethod(stdClass $method, string $suggestId = ""){
            $find_query = "SELECT * FROM Method WHERE type = ? AND JSON_EXTRACT(details,'$.address') = ?";
            $find_stmt = $this->client->prepare($find_query);
            if($find_stmt->execute([$method->type, $method->details->address]) && $find_stmt->rowCount() === 0){
                if(isset($method->type) && isset($method->category) && isset($method->percentage) && isset($method->details) && isset($method->details->address)){
                    $query = "INSERT INTO Method (id, category,type, percentage, details) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->client->prepare($query);
                    $hash = "";
                    if(!empty($suggestId)){
                        $hash = $suggestId;
                    }else{
                        $hash = generateHash();
                    }
                    if($stmt->execute([$hash, $method->category,$method->type, floatval($method->percentage), json_encode($method->details)])){
                        return $hash;
                    }
                }
            }
            return "";
        }

        private function storeBankingMethod(stdClass $method, string $suggestId = ""){
            $find_query = "SELECT * FROM Method WHERE type = ? AND JSON_EXTRACT(details,'$.curency') = ? ";
            $find_stmt = $this->client->prepare($find_query);
            if($find_stmt->execute([$method->type, $method->details->currency]) && $find_stmt->rowCount() === 0){
                if(isset($method->type) && isset($method->category) && isset($method->percentage) && isset($method->details)){
                    $query = "INSERT INTO Method (id, category,type, percentage, details) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->client->prepare($query);
                    $hash = "";
                    if(!empty($suggestId)){
                        $hash = $suggestId;
                    }else{
                        $hash = generateHash();
                    }
                    if($stmt->execute([ $hash, $method->category, $method->type, floatval($method->percentage), json_encode($method->details)])){
                        return $hash;
                    }
                }
            }
        
            return "";
        }

        private function storeTransferMethod(stdClass $method, string $suggestId = ""){
            $find_query = "SELECT * FROM Method WHERE type = ? AND JSON_EXTRACT(details,'$.curency') = ?";
            $find_stmt = $this->client->prepare($find_query);
            if($find_stmt->execute([$method->type, $method->details->currency]) && $find_stmt->rowCount() === 0){
                if(isset($method->type) && isset($method->category) && isset($method->percentage) && isset($method->details)){
                    $query = "INSERT INTO Method (id, category,type, percentage, details) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->client->prepare($query);
                    $hash = "";
                    if(!empty($suggestId)){
                        $hash = $suggestId;
                    }else{
                        $hash = generateHash();
                    }
                    if($stmt->execute([ $hash, $method->category, $method->type, floatval($method->percentage), json_encode($method->details)])){
                        return $hash;
                    }
                }
            }
            return "";
        }

        private function storeCryptoCurrencyMethod(stdClass $method, string $suggestId = ""){
            $find_query = "SELECT * FROM Method WHERE type = ?";
            $find_stmt = $this->client->prepare($find_query);
            if($find_stmt->execute([$method->type]) && $find_stmt->rowCount() === 0){
                if(isset($method->type) && isset($method->category) && isset($method->percentage) && isset($method->details)){
                    $query = "INSERT INTO Method (id, category,type, percentage, details) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $this->client->prepare($query);
                    $hash = "";
                    if(!empty($suggestId)){
                        $hash = $suggestId;
                    }else{
                        $hash = generateHash();
                    }
                    if($stmt->execute([ $hash, $method->category, $method->type, floatval($method->percentage), json_encode($method->details)])){
                        return $hash;
                    }
                }
            }
            return "";
        }

        public function storeMethod(stdClass $method, string $suggestId = ""){
            if($method->category === "banking"){
                return $this->storeBankingMethod($method, $suggestId);
            }
            else if($method->category === "mobile"){
                return $this->storeMobileMethod($method, $suggestId);
            }
            else if($method->category === "transfer"){
                return $this->storeTransferMethod($method, $suggestId);
            }
            else if($method->category === "cryptocurrency"){
                return $this->storeCryptoCurrencyMethod($method, $suggestId);
            }else{
                return "";
            }
        }
        
        public function deleteMethod(string $id){
            $query = "DELETE FROM Method WHERE id = ?";
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