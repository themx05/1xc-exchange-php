<?php

namespace Core;

use Models\RegistrationFeeTransaction;
use Models\Wallet;
use Models\WalletHistory;
use PDO;
use stdClass;

class WalletProvider extends Provider{
    public const WALLET_BUSINESS = "business";
    public const WALLET_STANDARD = "standard";
    public const TX_COMMISSION = "commission";
    public const TX_DEPOSIT = "deposit";
    public const TX_WITHDRAW = "withdraw";
    public const TX_NORMAL = "normal";

    /**
     * Generates signature of 1xc wallets.
     */
    private function generateSignature($length = 8){
        $prefix = "1XC";
        $items = "1234567890";
        $generated = $prefix;
        $count = strlen($items);

        for($i=0; $i<$length; $i++){
            $rand_pos = rand(0, $count-1);
            $generated .= substr($items, $rand_pos, 1);
        }
        return $generated;
    }

    public function saveRegistrationFeeInstant(
        string $userId, 
        string $wallet, 
        string $method, 
        string $reference, 
        float $amount, 
        string $currency, 
        int $time 
    ){
        $query = "INSERT INTO RegistrationFeeTransaction(id, data) VALUES (?,?)";
        $stmt = $this->client->prepare($query);
        $id = generateHash();
        $data = [
            'id' => $id,
            'user' => $userId,
            'wallet' => $wallet,
            'method' => $method,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'paymentDate' => $time
        ];

        $previous_entry = $this->getRegistrationFeeEntry($userId);

        if(isset($previous_entry)){
            return "";
        }
        
        if($stmt->execute([$id, json_encode($data)])){
            return $id;
        }
        return "";
    }

    public function getRegistrationFeeEntry(string $userId){
        $query = "SELECT * FROM RegistrationFeeTransaction WHERE JSON_EXTRACT(data, '$.user') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$userId]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return new RegistrationFeeTransaction(json_decode($item['data']));
        }
        return null;
    }

    public function createWallet(string $type = Wallet::TYPE_STANDARD , string $currency = 'XOF', float $initialBalance = 0, string $userId){
        $signature = $this->generateSignature();

        while( $this->getWalletById($signature) !== null ){
            $signature = $this->generateSignature();
        }

        $data = [
            'id' => $signature,
            'userId' => $userId,
            'type' => $type,
            'isMain' => false,
            'balance' => [
                'amount' => $initialBalance,
                'currency' => $currency
            ],
            'createdAt' => time(),
        ];

        // mark wallet as user's main wallet if he didn't have a wallet.
        $previousWallets = $this->getWalletsByUser($userId);
        if(count($previousWallets) === 0){
            $data['isMain'] = true;
        }

        $query = "INSERT INTO Wallets (id,data) VALUES(?,?)";
        
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$signature, json_encode($data)])){
            return $signature;
        }
        return "";
    }

    public function getWalletById(string $id){
        $query = "SELECT * FROM Wallets WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Wallet(json_decode($item['data']));
        }
        return null;
    }

    public function getBusinessWalletByUser(string $user){
        $query = "SELECT * FROM Wallets WHERE JSON_EXTRACT(data,'$.userId') = ? AND JSON_EXTRACT(data,'$.type') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$user, Wallet::TYPE_BUSINESS]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Wallet(json_decode($item['data']));
        }
        return null;
    }

    /**
     * Returns a user's wallets.
     */
    public function getWalletsByUser(string $user){
        $query = "SELECT * FROM Wallets WHERE JSON_EXTRACT(data,'$.userId') = ?";
        $stmt = $this->client->prepare($query);
        $parsed = [];
        if($stmt->execute([$user]) && $stmt->rowCount() > 0){
            $item =  null;
            while(($item = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($parsed, new Wallet(json_decode($item['data'])));
            }
        }
        return $parsed;
    }

    /**
     * Returns the main wallet of a user.
     */
    public function getMainUserWallet(string $user){
        $query = "SELECT * FROM Wallets WHERE JSON_EXTRACT(data,'$.userId') = ? AND JSON_EXTRACT(data,'$.isMain') = true";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$user]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Wallet(json_decode($item['data']));
        }
        return null;
    }

    public function getWallets(){
        $query = "SELECT * FROM Wallets ORDER BY JSON_EXTRACT(data,'$.creationDate') DESC";
        $stmt = $this->client->prepare($query);
        $parsed = [];
        if($stmt->execute() && $stmt->rowCount() > 0){
            $item =  null;
            while(($item = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($parsed, new Wallet(json_decode($item['data'])));
            }
        }
        return $parsed;
    }
    
    /**
     * Marks a wallet as the user's main wallet
     */
    public function markUserWalletAsMain(string $walletId, string $user){
        $wallets = $this->getWalletsByUser($user);
        if( count($wallets) > 0){
            foreach($wallets as $wallet){
                $stmt = $this->client->prepare("UPDATE Wallets SET data = ? WHERE id = ?");
                if($wallet instanceof Wallet){
                    if($wallet->id === $walletId){
                        $wallet->isMain = true;
                    }
                    else{
                        $wallet->isMain = false;
                    }
                    $stmt->execute([json_encode($wallet), $wallet->id]);
                }
            }
        }
        return true;
    }

    /*
    public function saveUserDeposit(string $wallet, string $method, string $reference, float $amount, string $currency){
        $data = [
            'id' => generateHash(),
            'walletId' => $wallet,
            'method' => $method,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'date' => time()
        ];
        $previous = $this->getDepositByReference($reference);
        if(isset($previous) && isset($previous['id']) && $previous['method'] === $method){
            return "";
        }
        $query = "INSERT INTO WalletDeposit(id, data) VALUES (?,?)";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$data['id'], json_encode($data)])){
            return $data['id'];
        }
        return "";
    }*/

    /*
    public function getDepositByReference(string $reference){
        $query = "SELECT * FROM  WalletDeposit WHERE JSON_EXTRACT(data,'$.reference') = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$reference]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($item['data'], true);
        }
        return null;
    }*/

    public function deposit(string $walletId, float $amount = 0, $currency= "XOF",string $memo="", string $type = WalletHistory::TYPE_NORMAL){
        if($amount > 0){
            $wallet = $this->getWalletById($walletId);

            if(isset($wallet)){
                if($currency !== $wallet->balance->currency){
                    $converter = new ConversionProvider();
                    $result = $converter->convert([
                        'source' => $currency,
                        'dest' => $wallet->balance->currency,
                        'amount' => $amount
                    ]);
    
                    if($result !== null){
                        $converted = $result->converted;
                        $amount = $converted;
                    }
                    else{
                        return "";
                    }
                }
    
                $wallet->credit($amount);
                
                $query = 'UPDATE Wallets SET data = ? WHERE id = ?';
                $stmt = $this->client->prepare($query);
    
                if($stmt->execute([json_encode($wallet), $wallet->id])){
                    /// Handle History Management.
                    return $this->createHistory($walletId, $amount, $memo, $type);
                }
            }
        }
        return "";
    }

    public function withdraw(string $walletId, float $amount = 0, $currency = "XOF", string $memo=""){
        if($amount > 0){
            $wallet = $this->getWalletById($walletId);

            if(isset($wallet)){
                if($currency !== $wallet->balance->currency){
                    $converter = new ConversionProvider();
                    $result = $converter->convert([
                        'source' => $currency,
                        'dest' => $wallet->balance->currency,
                        'amount' => $amount
                    ]);
    
                    if($result !== null){
                        $converted = $result->converted;
                        $amount = $converted;
                    }
                    else{
                        return "";
                    }
                }
                
                $debited = $wallet->debit($amount);
                
                if(!$debited){
                    return "";
                }

                $query = 'UPDATE Wallets SET data = ? WHERE id = ?';
                $stmt = $this->client->prepare($query);
    
                if($stmt->execute([json_encode($wallet), $wallet->id])){
                    /// Handle History Management.
                    return $this->createHistory($walletId, $amount, $memo);
                }
            }
        }
        return "";
    }

    public function createHistory(string $walletId, float $amount, string $memo, string $type = WalletHistory::TYPE_NORMAL){
        $wallet = $this->getWalletById($walletId);

        if($wallet !== null){
            $data = [
                'id' => generateHash(),
                'wallet' => $walletId,
                'type' => $type,
                'amount' => $amount,
                'currency' => $wallet->balance->currency,
                'memo' => $memo,
                'creationDate' => time()
            ];
            $query = "INSERT INTO WalletHistory(id, data) VALUES(?,?)";
            $stmt = $this->client->prepare($query);
            if($stmt->execute([$data['id'], json_encode($data)])){
                return $data['id'];
            }
        }
        return "";
    }

    public function getHistoriesByWallet(string $wallet){
        $query = "SELECT * FROM WalletHistory WHERE JSON_EXTRACT(data,'$.wallet') = ?";
        $stmt = $this->client->prepare($query);

        $parsed = [];
        if($stmt->execute([$wallet]) && $stmt->rowCount() > 0){
            $raw = null;
            while(($raw = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($parsed, new WalletHistory(json_decode($raw['data'])));
            }
        }
        return $parsed;
    }

    public function getHistoryById(string $id){
        $query = "SELECT * FROM WalletHistory WHERE id = ?";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return new WalletHistory(json_decode($item['data']));
        }
        return null;
    }
}

?>