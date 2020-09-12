<?php

namespace Core;

use Models\User;
use PDO;
use stdClass;
use Utils\Emails;
use Utils\Utils;

class UserProvider extends Provider{

    public function createProfile(stdClass $data){
        $email = htmlspecialchars($data->email);
        $firstName = $data->firstName;
        $lastName = $data->lastName;
        $gender = $data->gender;
        $password = $this->encryptPassword($data->password);
        
        $this->client->beginTransaction();
        $check_query = "SELECT id FROM Users WHERE JSON_EXTRACT(data,'$.email') = '$email'";
        $check_stmt = $this->client->prepare($check_query);
        if($check_stmt->rowCount() === 0){
            $user = [
                'id' => Utils::generateHash(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'gender' => $gender,
                'email' => $email,
                'country' => $data->country,
                'passwordHash' => $this->encryptPassword($password),
                'verified' => false,
                'isMerchant' => false,
                'createdAt' => time()
            ]; 
            $query = "INSERT INTO Users (id, data) VALUES (?,?)";
            $prepared = $this->client->prepare($query);
            $stmt = $prepared->execute([$user['id'], \json_encode($user)]);
            if($stmt !== null){
                if($this->logger !== null){
                    $this->logger->info("User created");
                }
                //Next step: Generate verification code
                $code = Utils::generateVerificationCode();
                $query = "INSERT INTO AccountVerificationCode (accountId, code) VALUES(?,?)";
                $codestmt = $this->client->prepare($query)->execute([$user['id'],$code]);
                if($codestmt != null){
                    //Next step: send verification email
                    if(Emails::profileVerification("$firstName $lastName", $email,$code)){
                        if($this->logger !== null){
                            $this->logger->info("Verification Email sent to email ".$email);
                        }
                        $this->client->commit();
                        return $user['id'];
                    }
                    if($this->logger !== null){
                        $this->logger->error("Failed to commit. Rolling Back");
                        $this->logger->info("Data Was: ".json_encode($data));
                    }
                }
            }
        }
        if($this->logger !== null){
            $this->logger->error("Failed to commit. Rolling Back");
            $this->logger->info("Data Was: ".json_encode($data));
        }
        $this->client->rollBack();
        return "";
    }

    public function activateProfile(string $code){
        $this->client->beginTransaction();
        $account_query = "SELECT * FROM Users INNER JOIN AccountVerificationCode ON Users.id = AccountVerificationCode.accountId WHERE AccountVerificationCode.code = '$code'";
        $stmt= $this->client->query($account_query);
        if($stmt->rowCount() > 0){
            $account = $stmt->fetch(PDO::FETCH_OBJ);
            $update_query = "UPDATE Users SET data = JSON_SET(data,'$.verified', true) WHERE id = ?";
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

    public function getProfileById(string $id, bool $omitPassword = true){
        $query = "SELECT * FROM Users WHERE id = ?";
        $stmt = $this->client->prepare($query);

        if($stmt->execute([$id])){
            $udata = $stmt->fetch(PDO::FETCH_ASSOC);
            $user = new User(json_decode($udata['data']));

            if($omitPassword){
                $user->passwordHash = "";
            }
            return $user;
        }else{
            return null;
        }
    }

    public function getAllProfiles(bool $omitPassword = true){
        $query = "SELECT * FROM Users";
        $stmt = $this->client->prepare($query);
        $users = [];
        if($stmt->execute()){
            $udata =  null;
            while(($udata = $stmt->fetch(PDO::FETCH_ASSOC))){
                $user = new User(json_decode($udata['data']));
                if($omitPassword){
                    $user->passwordHash = "";
                }
                array_push($users, $user);
            }
        }
        return $users; 
    }

    public function encryptPassword(string $pass){
        return hash("sha256", $pass);
    }

    public function updateCredentials(User $currentUser, stdClass $data): bool{
        if(isset($data->email) && isset($data->lastPassword) && isset($data->newPassword)){
            if($currentUser->passwordHash === $this->encryptPassword($data->lastPassword)){
                $currentUser->email  = filter_var($data->email, FILTER_VALIDATE_EMAIL);
                $currentUser->passwordHash = $this->encryptPassword($data->newPassword);
                $currentUser->updatedAt = time();
                $update_stmt  = $this->client->query("UPDATE Users SET data = ? WHERE id = ?");
                if($update_stmt->execute([json_encode($currentUser),$currentUser->id])){
                    return true;
                }
            }
        }
        return false;
    }

    public function enableProfile(string $userId){
        $update_user_query = "UPDATE Users SET data = JSON_SET(data,'$.status',?) WHERE id = ?";
        $update_user_stmt = $this->client->prepare($update_user_query);
        if($update_user_stmt->execute([ User::STATUS_ACTIVE, $userId])){
            return true;
        }
        return false;
    }

    public function disableProfile(string $userId){
        $update_user_query = "UPDATE Users SET data = JSON_SET(data,'$.status',?) WHERE id = ?";
        $update_user_stmt = $this->client->prepare($update_user_query);
        if($update_user_stmt->execute([User::STATUS_DISABLED, $userId])){
            return true;
        }
        return false;
    }

    public function getProfileByEmail(string $email, bool $omitPassword = true){
        $query = "SELECT * FROM Users WHERE JSON_EXTRACT(data,'$.email') = ?";
        $prepared = $this->client->prepare($query);

        if($prepared->execute([$email]) && $prepared->rowCount() > 0){
            $udata = $prepared->fetch(PDO::FETCH_ASSOC);
            $user = new User(json_decode($udata['data']));
            if($omitPassword){
                $user->passwordHash = "";
            }
            return $user;
        }

        return null;
    }

    public function markUserAsPartner(string $userId){
        $update_user_query = "UPDATE Users SET data = JSON_SET(data,'$.isMerchant',true) WHERE id = ?";
        $update_user_stmt = $this->client->prepare($update_user_query);
        if($update_user_stmt->execute([$userId])){
            return true; 
        }
        return false;
    }
}

?>