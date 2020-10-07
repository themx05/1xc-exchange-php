<?php
namespace Models;

use stdClass;

class Method{
    use LoadFromStd;
    public string $id;
    public string $category;
    public string $type;
    public string $label;
    public string $icon;
    public bool $allowBuy;
    public bool $allowSell;
    public int $addedAt;
    public int $updatedAt;
    public stdClass $details;

    public const CATEGORY_BANKING = "banking";
    public const CATEGORY_MOBILE = "mobile";
    public const CATEGORY_TRANSFER = "transfer";
    public const CATEGORY_CRYPTO = "cryptocurrency";

    public const TYPE_PERFECTMONEY = "perfectmoney";
    public const TYPE_MTN = "mtnmobilemoney";
    public const TYPE_MOOV = "moovmoney";
    public const TYPE_INTERNAL = "internal";

    public function __construct()
    {
        $this->details = new stdClass();
    }

    public function detailsAsBanking(){
        return new BankingDetails($this->details);
    }

    public function detailsAsCurrencyDetails(){
        return new CurrencyDetails($this->details);
    }

    public function detailsAsCrypto(){
        return new CryptoCurrencyDetails($this->details);
    }

    public function detailsAsMobile(){
        return new MobileDetails($this->details);
    }

    public function detailsAsAmountLimitation(){
        return new AmountLimitation($this->details);
    }

    public function getCurrency(){
        if($this->category === static::CATEGORY_CRYPTO){
            return strtoupper($this->type);
        }
        return strtoupper($this->detailsAsCurrencyDetails()->currency);
    }

    public function getAddress(): string{
        if($this->category === static::CATEGORY_MOBILE){
            return $this->detailsAsMobile()->address;
        }
        else if($this->category === Method::CATEGORY_BANKING || $this->category === Method::CATEGORY_TRANSFER){
            return $this->details->account;
        }
        return "";
    }

    public function getCountry(){
        if(isset($this->details->country)){
            return $this->details->country;
        }
        return null;
    }

    public static function typeFromFedaMode(string $mode){
        if($mode === "mtn"){
            return static::TYPE_MTN;
        }
        else if($mode === "moov"){
            return static::TYPE_MOOV;
        }
        return $mode;
    }
}

?>