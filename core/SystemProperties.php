<?php
namespace Core{

use PDO;
    use stdClass;

class SystemProperties{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function getSystemProperties():stdClass{
        $stmt = $this->client->query("SELECT * FROM SystemProperties LIMIT 1");
        if($stmt->rowCount() > 0){
            $props = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($props['properties']);
        }else{
            $this->saveDefault();
            return $this->getSystemProperties();
        }
    }

    public function save(array $config){
        $stmt = $this->client->prepare("INSERT INTO SystemProperties(id, properties) VALUES(?,?)");
        if($stmt->execute([generateHash(),json_encode($config)])){
            return true;
        }
        return false;
    }

    public function getBusinessWalletFee(){
        $props = $this->getSystemProperties();
        return $props->businessAccountFee;
    }

    public function saveDefault(){
        return $this->save([
            'businessAccountFee' => [
                'amount' => 0,
                'currency' => 'XOF'
            ],
            'authentication' => [
                'secret' => \randomString(64)
            ]
        ]);
    }

    public function updateSystemProperties(array $newConfig){
        $stmt = $this->client->prepare("UPDATE SystemProperties SET properties =  ?");
        if($stmt->execute([json_encode($newConfig)])){
            return true;
        }
        return false;
    }
}

}

?>