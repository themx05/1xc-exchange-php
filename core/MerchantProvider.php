<?php

namespace Core{

use PDO;

class MerchantProvider{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createBusinessProfile(string $userId, array $data){
        if(!isset($data['country'])){
            return "";
        }
        if(!isset($data['name'])){
            return "";
        }
        if(!isset($data['city'])){
            return "";
        }
        if(!isset($data['phone'])){
            return "";
        }
        if(!isset($data['email'])){
            return "";
        }
        if(!isset($data['documents'])){
            return "";
        }

        $data['userId'] = $userId;
        $data['creationDate'] = time();
        $data['status'] = 'pending';

        $query = "INSERT INTO MerchantProfile(id, data) VALUES(?,?)";
        $id = generateHash();
        $data['id'] = $id;
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id, json_encode($data)])){
            return $id;
        }
        return "";
    }

    public function areDocumentsVerified(string $businessId){
        $business = $this->getProfileById($businessId);
        if(isset($business)){
            $docs = $business['documents'];
            foreach($docs as $doc){
                if(!isset($doc['verified']) || $doc['verified'] == false){
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    public function getProfiles(){
        $query = "SELECT * FROM MerchantProfile";
        $stmt = $this->client->prepare($query);

        if($stmt->execute() && $stmt->rowCount()){
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $profiles = [];
            foreach($raw as $key => $value){
                array_push($profiles,json_decode($value['data'], true));
            }
            return $profiles;
        }

        return null;
    }

    public function getProfileById(string $id){
        $query = "SELECT * FROM MerchantProfile WHERE id = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($profile['data'], true);
        }

        return null;
    }

    public function getBusinessProfileByUser(string $user){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.userId') = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$user]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($profile['data'],true);
        }

        return null;
    }

    public function getBusinessProfileByEmail(string $email){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.email') = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$email]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($profile['data'],true);
        }

        return null;
    } 

    public function updateProfile(string $id, array $data){
        $data['id'] = $id;
        $data['updateDate'] = time();

        $query = "UPDATE MerchantProfile SET data = ? WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([json_encode($data), $id])){
            return true;
        }
        return false;
    }

    public function approveProfile($profileId){
        $profile = $this->getProfileById($profileId);
        if(isset($profile)){
            $profile['status'] = 'verified';
            $profile['verificationDate'] = time();
            $userProvider = new UserProvider($this->client);
            if($this->updateProfile($profileId, $profile)){
                return $userProvider->markUserAsPartner($profile['userId']);
            }
        }
        return false;
    }

    public function deleteProfile($profileId){
        $query = "DELETE FROM MerchantProfile WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$profileId])){
          return true;
        }
        return false;
    }
}

}

?>