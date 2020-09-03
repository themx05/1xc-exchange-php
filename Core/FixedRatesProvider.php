<?php

/**
 * Currently unused. Will help in future to fix some exchange rates.
 */
namespace Core;

use PDO;
use Utils\Utils;

class FixedRatesProvider extends Provider{

    public function fixRate(string $from, float $fromAmount, string $to, string $toAmount){
        $data=[
            'id' => Utils::generateHash(),
            'from' => [
                'amount' => $fromAmount,
                'currency' => strtoupper($from)
            ],
            'to' => [
                'amount' => $toAmount,
                'currency' => strtoupper($to)
            ],
            'createdAt' => time()
        ];

        $query = "INSERT INTO FixedRates(id, data) VALUES(?,?)";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$data['id'], json_encode($data)])){
            return $data['id'];
        }
        return "";
    }

    public function updateFixedRate(string $id, float $sourceAmount, float $destAmount){
        $data = $this->getRateById($id);

        if($sourceAmount <= 0 || $destAmount <= 0){
            return false;
        }

        $data['from']['amount'] = $sourceAmount;
        $data['to']['amount'] = $destAmount;
        $data['updatedAt'] = time();

        $query = "UPDATE FixedRates SET data = ? WHERE id = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([json_encode($data), $data['id']])){
            return true;
        }
        return false;
    }

    public function removeFixedRate(string $id){
        $query = "DELETE FROM FixedRates WHERE id = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$id])){
            return true;
        }
        return false;
    }

    public function getRates(){
        $query = "SELECT data FROM FixedRates";
        $stmt = $this->client->prepare($query);

        if($stmt->execute() && $stmt->rowCount() > 0){
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $parsed = [];
            foreach($entries as $entry){
                array_push($parsed,json_decode($entry['data'], true));
            }
            return $parsed;
        }
        return [];
    }

    public function getRateById(string $id){
        $query = "SELECT data FROM FixedRates WHERE JSON_EXTRACT(data,'$.id') = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($entry['data'], true);
        }
        return null;
    }

    public function getRateData(string $source, string $dest){
        $query = "SELECT data FROM FixedRates WHERE JSON_EXTRACT(data,'$.from.currency') = ? AND JSON_EXTRACT(data,'$.to.currency') = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$source, $dest]) && $stmt->rowCount() > 0){
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($entry['data'], true);
        }
        return null;
    }

}

?>