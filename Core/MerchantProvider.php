<?php

namespace Core;

use Models\BusinessProfile;
use PDO;
use stdClass;

class MerchantProvider{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createBusinessProfile(string $userId, stdClass $data){
        if(!isset($data->country)){
            return "";
        }
        if(!isset($data->name)){
            return "";
        }
        if(!isset($data->city)){
            return "";
        }
        if(!isset($data->phone)){
            return "";
        }
        if(!isset($data->email)){
            return "";
        }
        if(!isset($data->documents)){
            return "";
        }

        $data->userId = $userId;
        $data->creationDate = time();
        $data->status = 'pending';

        $query = "INSERT INTO MerchantProfile(id, data) VALUES(?,?)";
        $id = generateHash();
        $data->id = $id;
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id, json_encode($data)])){
            return $id;
        }
        return "";
    }

    public function areDocumentsVerified(string $businessId){
        $business = $this->getProfileById($businessId);
        if(isset($business)){
            $docs = $business->documents;
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
        $profiles = [];
        if($stmt->execute() && $stmt->rowCount()){
            $raw = null;
            while(($raw = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($profiles,new BusinessProfile(json_decode($raw['data'])));
            }
        }
        return $profiles;
    }

    public function getProfileById(string $id){
        $query = "SELECT * FROM MerchantProfile WHERE id = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    }

    public function getProfileByName(string $name){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.name') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$name]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    }

    public function getProfileByEmail(string $email){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.email') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$email]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    }

    public function getProfileByPhone(string $phone){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.phone') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$phone]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    }

    public function getBusinessProfileByUser(string $user){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.userId') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$user]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    }

    public function getBusinessProfileByEmail(string $email){
        $query = "SELECT * FROM MerchantProfile WHERE JSON_EXTRACT(data,'$.email') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$email]) && $stmt->rowCount() > 0){
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return new BusinessProfile(json_decode($profile['data']));
        }
        return null;
    } 

    public function updateProfile(BusinessProfile $data){
        $data->updateDate = time();

        $query = "UPDATE MerchantProfile SET data = ? WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([json_encode($data), $data->id])){
            return true;
        }
        return false;
    }

    public function approveProfile($profileId){
        $profile = $this->getProfileById($profileId);
        if(isset($profile)){
            $profile->status = BusinessProfile::STATUS_VERIFIED;
            $profile->verificationDate = time();
            $userProvider = new UserProvider($this->client);
            if($this->updateProfile($profile)){
                return $userProvider->markUserAsPartner($profile->userId);
                return true;
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
?>