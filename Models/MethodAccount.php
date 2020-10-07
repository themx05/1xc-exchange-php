<?php
namespace Models;
use stdClass;
class MethodAccount{
    use LoadFromStd;
    public string $id;
    public string $type;
    public stdClass $details;

    public const TYPE_PERFECT = "perfectmoney";
    public const TYPE_COINBASE = "coinbase";
    public const TYPE_FEDAPAY = "fedapay";

    public function __construct()
    {
        $this->details = new stdClass();
    }

    public function detailsAsPerfectMoney(){
        return new PerfectmoneyAccount($this->details);
    }

    public function detailsAsCoinbase(){
        return new CoinbaseAccount($this->details);
    }

    public function detailsAsFedapay(){
        return new FedapayAccount($this->details);
    }
}

?>