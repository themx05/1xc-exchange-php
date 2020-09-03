<?php
namespace Core;

use Models\SystemProps;
use PDO;
use stdClass;
use Utils\Utils;

class SystemProperties extends Provider{
    
    public function getSystemProperties():SystemProps{
        $stmt = $this->client->query("SELECT * FROM SystemProps LIMIT 1");
        if($stmt->rowCount() > 0){
            $props = $stmt->fetch(PDO::FETCH_ASSOC);
            return new SystemProps(json_decode($props['data']));
        }else{
            $this->saveDefault();
            return $this->getSystemProperties();
        }
    }

    public function save(stdClass $config){
        $stmt = $this->client->prepare("INSERT INTO SystemProps(id, data) VALUES(?,?)");
        if($stmt->execute([Utils::generateHash(),json_encode($config)])){
            return true;
        }
        return false;
    }

    public function getBusinessWalletFee(){
        $props = $this->getSystemProperties();
        return $props->businessAccountFee;
    }

    public function saveDefault(){
        return $this->save(
            json_decode(
                json_encode(
                    [
                        'businessAccountFee' => [
                            'amount' => 0,
                            'currency' => 'XOF'
                        ],
                        'authentication' => [
                            'secret' => Utils::randomString(64)
                        ]
                    ]
                )
            )
        );  
    }

    public function updateSystemProperties(SystemProps $newConfig){
        $stmt = $this->client->prepare("UPDATE SystemProps SET data =  ?");
        if($stmt->execute([json_encode($newConfig)])){
            return true;
        }
        return false;
    }
}

?>