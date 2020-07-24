<?php

namespace Core{

    use PDO;
    use stdClass;

class SystemAdminProvider{

        public $client;

        public function __construct(PDO $client){
            $this->client = $client;
        }

        public function getAdminById(string $id): array{
            $query = "SELECT * FROM Admins WHERE id = ?";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$id])){
                if($stmt->rowCount() > 0){
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $admin;
                }
            }
            return null;
        }

        public function getAdmins(): array{
            $query = "SELECT * FROM Admins ORDER BY insertionDate ASC";
            $stmt = $this->client->prepare($query);
            if($stmt->execute()){
                $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $admins;
            }
            return null;
        }

        public function getAdminByCredential(stdClass $creds):array{
            if(isset($creds->alias) && isset($creds->password)){
                $query = "SELECT * FROM Admins WHERE alias = ? AND passwordHash = ?";
                $stmt = $this->client->prepare($query);
                if($stmt->execute([$creds->alias, $this->hashPassword($creds->password)])){
                    if($stmt->rowCount() > 0){
                        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                        return $admin;
                    }
                }
            }
            return null;
        }

        public function getRootAdmin(): array{
            $query = "SELECT * FROM Admins ORDER BY insertionDate DESC LIMIT 1";
            $stmt = $this->client->query($query);
            if($stmt->rowCount() > 0){
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                return $admin;
            }
            return null;
        }

        public function hashPassword(string $password){
            return hash("sha256", $password);
        }

        public function createAdmin(stdClass $data = null){
            if(isset($data->alias) && isset($data->password) && isset($data->firstname) && isset($data->lastname) && isset($data->gender)){
                $query = "INSERT INTO Admins (id, firstName, lastName, gender, alias, passwordHash) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $this->client->prepare($query);

                $id = generateHash();
                if(
                    $stmt->execute([
                        $id,
                        protectString($data->firstname),
                        protectString($data->lastname),
                        in_array($data->gender, ['male','female']) ? $data->gender : 'male',
                        protectString($data->alias),
                        $this->hashPassword($data->password)
                    ])
                ){
                    return $id;
                }
            }
            return "";
        }

        public function updateProfile(string $id, stdClass $data){
            if(isset($data->firstName) && isset($data->lastName) && isset($data->gender)){
                $update_query = "UPDATE Admins SET firstName = ?, lastName = ?, gender = ? WHERE id = ?";
                $update_stmt = $this->client->prepare($update_query);
    
                if($update_stmt->execute([$data->firstName, $data->lastName, $data->gender, $id])){
                    return true;
                }
            }
            return false;
        }

        public function updatePassword(string $id, stdClass $data){
            if(isset($data->alias) &&  isset($data->lastPassword) && isset($data->newPassword)){
                $select_admin_query = "SELECT * FROM Admins WHERE id = ? AND passwordHash = ?";
                $select_admin_stmt = $this->client->prepare($select_admin_query);
                if($select_admin_stmt->execute([$id, $this->hashPassword($data->lastPassword)]) && $select_admin_stmt->rowCount() > 0) {
                    $admin_data = $select_admin_stmt->fetch(PDO::FETCH_OBJ);
                    $admin_data->alias = $data->alias;
                    $admin_data->passwordHash = $this->hashPassword($data->newPassword);
    
                    $update_query = "UPDATE Admins SET alias = ?, passwordHash = ? WHERE id = ?";
    
                    $update_stmt = $this->client->prepare($update_query);
    
                    if($update_stmt->execute([$admin_data->alias, $admin_data->passwordHash, $admin_data->id])){
                        return true;
                    }
    
                }
            }
            return false;
        }
    
    
    }
}


?>