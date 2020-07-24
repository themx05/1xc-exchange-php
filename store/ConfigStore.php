<?php

namespace App;

use DbClient;
use PDO;
use PDOException;

class SystemConfig {
    const sqlite_filename = "./config_database.db";
    private $pdo;

    public function __construct(){
        try{
            $this->pdo = new PDO("sqlite:".SystemConfig::sqlite_filename);
            $this->initialize();
        }catch(PDOException $ex){
            die($ex);
        }
    }

    public function initialize(){
        $script = "CREATE IF NOT EXISTS TABLE Configuration(
            id varchar(255) not null,
            attrs text not null
        )";
        
        return $this->pdo->query($script);
    }

    public function isConfigured(){
        $query = "SELECT * FROM Configuration";
        $stmt = $this->pdo->prepare($query);
        if($stmt->execute()){
            return $stmt->rowCount() > 0 ;
        }
        return false;
    }


    public function configure( array $configuration){
        if(!$this->isConfigured()){
            $query = "INSERT INTO Configuration (id, attrs) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute([generateHash(), json_encode($configuration)]);
        }
        return true;
    }

    public function updateConfiguration(array $configuration){
        if($this->isConfigured()){
            $query = "UPDATE Configuration SET attrs = ? WHERE id NOT NULL ";
            $stmt = $this->pdo->prepare($query);
            if($stmt->execute([json_encode($configuration)])){
                return true;
            }
        }
        return false;
    }

    /**
     * Return the system configuration (email and database)
     */
    public function getSystemConfiguration(){
        if($this->isConfigured()){
            $query = "SELECT attrs FROM Configuration LIMIT 1";
            $stmt = $this->pdo->query($query);
            if($stmt){
                if($stmt->rowCount() > 0){
                    $config = json_decode($stmt->fetch(PDO::FETCH_ASSOC))['attrs'];
                    return $config;
                }
            }
        }
        return [];
    }

    /**
     * Returns the database configuration 
     */
    public function getDatabaseConfiguration(){
        if($this->isConfigured()){
            return $this->getSystemConfiguration()['database'];
        }
        return [];
    }

    /**
     * Returns the previously stored Email configuration
     */
    public function getEmailConfiguration(){
        if($this->isConfigured()){
            return $this->getSystemConfiguration()['email'];
        }
        return [];
    }

    /**
     * Check if database configuration is set
     */
    public function isDatabaseConfigured(){
        $config = $this->getDatabaseConfiguration();
        return isset($config['host']) && 
            isset($config['port']) && 
            isset($config['username']) && 
            isset($config['password']) && 
            isset($config['database']);
    }

    /**
     * Chec is the email configuration is set
     */
    public function isEmailConfigured(){
        $config = $this->getEmailConfiguration();
        return isset($config['host']) && 
            isset($config['port']) &&
            isset($config['user']) &&
            isset($config['password']);
    }

    /**
     * Initializes the configured database
     * By creating required tables.
     */
    public function setupDatabase(){
        if(is_file("./database.sql")){
            $script = file_get_contents("./database.sql");
            $db = $db = DbClient::prepareInstance($this->getDatabaseConfiguration());
            return $db->query($script) !== false;
        }
        return false;
    }

    public function createInitialAdmin(array $admin){
        $query = "INSERT INTO Admins (id, firstName, lastName, alias, passwordHash) VALUES(?, ?, ?, ?, ?)";
        $db = DbClient::getInstance();
        if($this->isDatabaseConfigured()){
            $db = DbClient::prepareInstance($this->getDatabaseConfiguration());
        }

        $stmt = $db->prepare($query);
        return $stmt->execute([
            generateHash(), 
            protectString($admin['firstName']),
            protectString($admin['lastName']),
            protectString($admin['alias']),
            hash('sha256', $admin['password'])
        ]);
    }

    public function __destruct(){
        $pdo = null;
    }
}
?>