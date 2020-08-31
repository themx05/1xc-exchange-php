<?php

namespace Core{

use PDO;
    use stdClass;

class UserProvider{
        public $client;

        public function __construct(PDO $client){
            $this->client = $client;
        }

        public function createProfile(stdClass $data){
            global $logger;
            $email = htmlspecialchars($data->email);
            $firstName = $data->firstName;
            $lastName = $data->lastName;
            $gender = $data->gender;
            $password = $this->encryptPassword($data->password);
          
            $this->client->beginTransaction();
            $check_query = "SELECT id FROM Users WHERE email = '$email'";
            $check_stmt = $this->client->prepare($check_query);
            if($check_stmt->rowCount() === 0){
                $query = "INSERT INTO Users (id, firstName, lastName, gender, email, passwordHash, country) VALUES (?,?,?,?,?,?,?)";
                $prepared = $this->client->prepare($query);
                $id = generateHash();
                $stmt = $prepared->execute([$id,$firstName,$lastName, $gender, $email,$password, $data->country ]);
                if($stmt !== null){
                    $logger->info("Insertion Done.");
                    //Next step: Generate verification code
                    $code = generateVerificationCode();
                    $query = "INSERT INTO AccountVerificationCode (accountId, code) VALUES(?,?)";
                    $codestmt = $this->client->prepare($query)->execute([$id,$code]);
                    if($codestmt != null){
                        //Next step: send verification email
                        $logger->info("Validation Code generated.");
                        if(sendVerificationEmail("$firstName $lastName", $email,$code)){
                            $logger->info("Verification Email sent to email ".$email);
                            $this->client->commit();
                            return $id;
                        }
                    }
                }
            }
            $logger->error("Failed to commit. Rolling Back");
            $logger->info("Data Was: ".json_encode($data));
            $this->client->rollBack();
            return "";
        }

        public function activateProfile(string $code){
            $this->client->beginTransaction();
            $account_query = "SELECT * FROM Users INNER JOIN AccountVerificationCode ON Users.id = AccountVerificationCode.accountId WHERE AccountVerificationCode.code = '$code'";
            $stmt= $this->client->query($account_query);
            if($stmt->rowCount() > 0){
                $account = $stmt->fetch(PDO::FETCH_OBJ);
                $update_query = "UPDATE Users SET verified = 1 WHERE id = ?";
                $update_stmt = $this->client->prepare($update_query);
                if($update_stmt->execute([$account->id])){
                    $deletion = "DELETE FROM AccountVerificationCode WHERE accountId = ?";
                    $deletion_stmt = $this->client->prepare($deletion);
                    if($deletion_stmt->execute([$account->id])){
                        $this->client->commit();
                        return true;
                    }
                }
            }
            $this->client->rollBack();
            return false;
        }

        public function getProfileById(string $id){
            $query = "SELECT id, firstName, lastName, gender, email, status, verified, insertionDate, isMerchant FROM Users WHERE id = ?";
            $stmt = $this->client->prepare($query);
    
            if($stmt->execute([$id])){
                $udata = $stmt->fetch(PDO::FETCH_ASSOC);
                return $udata;
            }else{
                return null;
            }
        }

        public function getAllProfiles(){
            $query = "SELECT id, firstName, lastName, gender, email, status, verified, insertionDate, isMerchant FROM Users";
            $stmt = $this->client->prepare($query);
            if($stmt->execute()){
                $udata = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $udata;
            }else{
                return null; 
            }
        }

        public function encryptPassword(string $pass){
            return hash("sha256", $pass);
        }

        public function updateCredentials(string $uid, stdClass $data): bool{
            $session_user = getUser();
            if(isset($data->email) && isset($data->lastPassword) && isset($data->newPassword)){
                $select_user_query = "SELECT * FROM Users WHERE id = ? AND passwordHash = ?";
                $select_user_stmt = $this->client->prepare($select_user_query);
                if($select_user_stmt->execute([$uid,$this->encryptPassword($data->lastPassword)]) && $select_user_stmt->rowCount() > 0){
                    $newEmail = filter_var($data->email,FILTER_VALIDATE_EMAIL);
                    $newPassword = $this->encryptPassword($data->newPassword);
                    $update_user_query = "UPDATE Users SET email = ?, passwordHash = ? WHERE id = ?";
                    $update_user_stmt = $this->client->prepare($update_user_query);
                    if($update_user_stmt->execute([$newEmail, $newPassword, $uid])){
                        return true;
                    }
                }
            }
            return false;
        }

        public function enableProfile(string $userId){
            $update_user_query = "UPDATE Users SET status = 'active' WHERE id = ?";
            $update_user_stmt = $this->client->prepare($update_user_query);
            if($update_user_stmt->execute([$userId])){
                return true;
            }
            return false;
        }

        public function disableProfile(string $userId){
            $update_user_query = "UPDATE Users SET status = 'disabled' WHERE id = ?";
            $update_user_stmt = $this->client->prepare($update_user_query);
            if($update_user_stmt->execute([$userId])){
                return true;
            }
            return false;
        }

        public function getProfileByEmail(string $email){
            $query = "SELECT * FROM Users WHERE email = ?";
            $prepared = $this->client->prepare($query);
    
            if($prepared->execute([$email]) && $prepared->rowCount() > 0){
                $user = $prepared->fetch(PDO::FETCH_ASSOC);
                return $user;
            }

            return null;
        }

        public function markUserAsPartner(string $userId){
            $update_user_query = "UPDATE Users SET isMerchant = true WHERE id = ?";
            $update_user_stmt = $this->client->prepare($update_user_query);
            if($update_user_stmt->execute([$userId])){
                return true; 
            }
            return false;
        }
    }
}

?>