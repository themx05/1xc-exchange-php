<?php

namespace Utils\Coinbase;

class SendTransaction{
    public $to;
    public $amount;
    public $currency;
    public $fee;
    public $description;
    public $idem;

    public function __construct(
        string $to, 
        float $amount, 
        string $currency,
        string $description = ""){
        
        $this->to = $to;
        $this->amount = ceil($amount * 100000000) / 100000000;
        $this->currency = $currency;
        $this->description = $description;
    }

    public function setFee($fee){
        $this->fee = ceil($fee * 100000000) / 100000000;
    }

    public function __toString(){
        $data = [];
        $data['type'] = 'send';
        $data['to'] = $this->to;
        $data['amount'] = number_format($this->amount, 8);
        $data['currency'] = $this->currency;
        
        if(isset($this->description)){
            $data['description'] = $this->description;
        }

        if(isset($this->fee)){
            $data['fee'] = number_format($this->fee, 8);
        }
        
        if(isset($this->idem)){
            $data['idem'] = $this->idem;
        }
        return json_encode($data);
    }
}

?>