<?php
namespace Models;
class Wallet{
    use LoadFromStd;

    public string $id;
    public string $userId;
    public string $type;
    public bool $isMain;
    public Money $balance;
    public int $createdAt;

    public const TYPE_BUSINESS = "business";
    public const TYPE_STANDARD = "standard";

    public function credit(float $amount){
        $this->balance->amount += $amount;
    }

    public function debit(float $amount){
        if($this->canDebit($amount)){
            $this->balance->amount -= $amount;
            return true;
        }
        return false;
    }

    public function canDebit(float $amount){
        return $this->balance->amount >= $amount;
    }

}
?>