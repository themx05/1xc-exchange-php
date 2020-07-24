<?php

function exactRound(float $val){
    $rounded = round($val, PHP_ROUND_HALF_DOWN);
    if($rounded > $val){
    	$rounded = $rounded - 0.01;
    }
    return $rounded;
}

class PerfectMoneySpend{
    public $memo;
    public $payer;
    public $payee;
    public $amount;
    public $paymentId;

    public function __construct(string $payer, string $payee, float $amount, string $paymentId, string $memo){
        $this->payer = $payer;
        $this->payee = $payee;
        $this->amount = $amount;
        $this->paymentId = $paymentId;
        $this->memo = $memo;
    }
}

class PerfectMoney{
    public $pmId;
    public $password;

    public function __construct(string $pmId, string $password){
        $this->pmId = $pmId;
        $this->password = $password;
    }

    public function getBalance(string $accountId){
        // trying to open URL to process PerfectMoney Spend request
        $f=fopen('https://perfectmoney.com/acct/balance.asp?AccountID='.$this->pmId.'&PassPhrase='.$this->password, 'rb');

        if($f===false){
            return null;
        }

        // getting data
        $out=array(); $out="";
        while(!feof($f)) $out.=fgets($f);

        fclose($f);

        // searching for hidden fields
        if(!preg_match_all("/<input name='(.*)' type='hidden' value='(.*)'>/", $out, $result, PREG_SET_ORDER)){
            return null;
        }

        // putting data to array
        $ar=[];
        foreach($result as $item){
            $key=$item[1];
            $ar[$key]=$item[2];
        }

        return doubleval($ar[$accountId]);
    }

    public function spend(PerfectMoneySpend $spend){

        $units = "";
        if(substr($spend->payer,0, 1) === "U" ){
            $units = "USD";
        }
        else if(substr($spend->payer,0, 1) === "E" ){
            $units = "EUR";
        }
        else if(substr($spend->payer,0, 1) === "B" ){
            $units = "BTC";
        }
        $spend->amount = exactRound($spend->amount); // Round to 2 decimals after the point.
        $url = 'https://perfectmoney.com/acct/confirm.asp?AccountID='.$this->pmId.'&PassPhrase='.$this->password.'&Payer_Account='.$spend->payer.'&Payee_Account='.$spend->payee.'&Amount='.$spend->amount.'&PAYMENT_ID='.$spend->paymentId;
        if(!empty($spend->memo)){
            $url = $url."&memo=".$spend->memo;
        }
        // trying to open URL to process PerfectMoney Spend request
        $f=fopen($url, 'rb');

        if($f===false){
            return null;
        }

        // getting data
        $out=array(); $out="";
        while(!feof($f)) $out.=fgets($f);
        fclose($f);

        // searching for hidden fields
        if(!preg_match_all("/<input name='(.*)' type='hidden' value='(.*)'>/", $out, $result, PREG_SET_ORDER)){
            return null;
        }

        $ar = [];
        foreach($result as $item){
            $key=$item[1];
            $ar[$key]=$item[2];
        }

        if(isset($ar['ERROR'])){
            return null;
        }

        if(isset($ar['PAYMENT_BATCH_NUM']) & isset($ar['PAYMENT_ID'])){
            $data = new ConfirmationData(
                generateHash(),
                "perfectmoney",
                $ar['PAYMENT_ID'],
                $spend->amount,
                $units,
                $spend->payer,
                $spend->payee,
                $ar['PAYMENT_BATCH_NUM'],
                time()
            );
            return $data;
        }
        return null;
    }
}

?>