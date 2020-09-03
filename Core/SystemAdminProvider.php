<?php

namespace Core;

use Models\SystemAdmin;
use PDO;
use stdClass;
use Utils\Utils;

class SystemAdminProvider extends Provider{

    public function getAdminById(string $id){
        $query = "SELECT * FROM Admins WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id])){
            if($stmt->rowCount() > 0){
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                return new SystemAdmin(json_decode($admin['data']));
            }
        }
        return null;
    }

    public function getAdmins(): array{
        $query = "SELECT * FROM Admins";
        $stmt = $this->client->prepare($query);
        $admins = [];
        if($stmt->execute()){
            $ad = null;
            while(($ad = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($admins, new SystemAdmin(json_decode($ad['data'])));
            }
        }
        return $admins;
    }

    public function getAdminByCredential(stdClass $creds){
        if(isset($creds->alias) && isset($creds->password)){
            $query = "SELECT * FROM Admins WHERE alias = ? AND passwordHash = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$creds->alias, $this->hashPassword($creds->password)])){
                if($stmt->rowCount() > 0){
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    return new SystemAdmin(json_decode($admin['data']));
                }
            }
        }
        return null;
    }

    public function getRootAdmin(){
        $query = "SELECT * FROM Admins where JSON_EXTRACT(data,'$.isRoot') = true";
        $stmt = $this->client->query($query);
        if($stmt->rowCount() > 0){
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return new SystemAdmin(json_decode($admin['data']));
        }
        return null;
    }

    public function hashPassword(string $password){
        return hash("sha256", $password);
    }

    public function createAdmin(stdClass $data = null){
        if(isset($data->alias) && isset($data->password) && isset($data->firstName) && isset($data->lastName) && isset($data->gender)){
            $query = "INSERT INTO Admins (id, data) VALUES (?, ?)";
            $stmt = $this->client->prepare($query);

            $id = Utils::generateHash();
            $data->id = $id;
            $data->firstName = Utils::protectString($data->firstName);
            $data->lastName = Utils::protectString($data->lastName);
            $data->passwordHash = $this->hashPassword($data->password);
            $data->gender = in_array($data->gender, ['male','female']) ? $data->gender : 'male';

            $root = $this->getRootAdmin();
            if($root !== null){
                $data->isRoot = false;
            }
            else{
                $data->isRoot = true;
            }
            if(
                $stmt->execute([
                    $id,
                    json_encode($data)
                ])
            ){
                return $id;
            }
        }
        return "";
    }

    public function updateProfile(SystemAdmin $data){
        if(isset($data->id) && isset($data->firstName) && isset($data->lastName) && isset($data->gender)){
            $update_query = "UPDATE Admins SET data = ? WHERE id = ?";
            $update_stmt = $this->client->prepare($update_query);
            if($update_stmt->execute([\json_encode($data), $data->id])){
                return true;
            }
        }
        return false;
    }

    public function updatePassword(SystemAdmin $data){
        if(isset($data->id) && isset($data->alias) &&  isset($data->passwordHash)){
            $data->passwordHash = $this->hashPassword($data->passwordHash);
            $update_query = "UPDATE Admins SET data = ? WHERE id = ?";
            $update_stmt = $this->client->prepare($update_query);
            if($update_stmt->execute([\json_encode($data), $data->id])){
                return true;
            }
        }
        return false;
    }
}
?>