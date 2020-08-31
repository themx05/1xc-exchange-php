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

    public function detailsAsBanking(){
        return new BankingDetails($this->details);
    }

    public function detailsAsCrypto(){
        return new CryptoCurrencyDetails($this->details);
    }

    public function detailsAsMobile(){
        return new MobileDetails($this->details);
    }

}

?>