<?php
namespace Models;

use stdClass;

class Ticket{
    use LoadFromStd;

    public string $id;
    public string $userId;
    public Method $source;
    public Method $dest;
    public float $amount;
    public float $rate;
    public string $address;
    public string $status;
    public bool $allowed;
    public bool $enableCommission;
    public int $validity = 60;
    public int $emittedAt;
    public int $confirmedAt;
    public int $paidAt;
    public int $cancelledAt;

    public const STATUS_PENDING = "pending";
    public const STATUS_CONFIRMED = "confirmed";
    public const STATUS_CANCELLED = "cancelled";
    public const STATUS_PAID = "paid";

    public function __construct()
    {
        $this->source = new Method();
        $this->dest = new Method();
    }

    public function isPending(){
        return $this->status === static::STATUS_PENDING;
    }

    public function isConfirmed(){
        return $this->status === static::STATUS_CONFIRMED;
    }

    public function isCancelled(){
        return $this->status === static::STATUS_CANCELLED;
    }

    public function isPaid(){
        return $this->status === static::STATUS_PAID;
    }

    /**
     * Check if a ticket has expired
     */
    public function hasExpired(){
        return $this->isPending() && ($this->emittedAt + $this->validity*60 < time());
    }

    public function getSendingFees(){
        $global = $this->totalFees();
        $staticFee = doubleval($this->dest->detailsAsAmountLimitation()->staticFee);
        $dynamicFee = doubleval($this->dest->detailsAsAmountLimitation()->dynamicFee);

        $fees = $staticFee + ($global * $dynamicFee/100);
        return $fees;
    }

    public function getEmitterCommission(){
        $global = $this->totalFees();
        $gain = doubleval($this->dest->detailsAsAmountLimitation()->percentage);
        $emitterBonus = doubleval($this->dest->detailsAsAmountLimitation()->emitterFee);

        return (($global * $gain/100) * $emitterBonus/100);
    }
    
    public function totalFees(){
        $global = $this->totalFees();
        $staticFee = doubleval($this->dest->detailsAsAmountLimitation()->staticFee);
        $dynamicFee = doubleval($this->dest->detailsAsAmountLimitation()->dynamicFee);
        $gain = doubleval($this->dest->detailsAsAmountLimitation()->percentage);

        return $staticFee + ($global * $gain/100) + ($global * $dynamicFee/100);
    }

    public function rawAmountToSend(){
        return doubleval($this->amount * $this->rate);
    }

    public function amountWithoutFees(){
        return $this->rawAmountToSend() - $this->totalFees();
    }

    public function getLabel(string $join = "to"){
        return $this->source->label." $join ".$this->dest->label;
    }
}
?>