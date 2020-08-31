<?php
namespace Core;
class ConfirmationData {
    public $id;
    public $type;
    public $paymentId;
    public $amount;
    public $units;
    public $source;
    public $destination;
    public $transactionId;
    public $timestamp;

    public $isDone = true;
    public $isPending  = false;
    
    public function __construct(
        string $id,
        string $type, 
        string $paymentId, 
        float $amount, 
        string $units, 
        string $source, 
        string $destination, 
        string $transactionId, 
        int $timestamp
    ){
        $this->id = $id;
        $this->type = $type;
        $this->paymentId = $paymentId;
        $this->amount = $amount;
        $this->units = $units;
        $this->source = $source;
        $this->destination = $destination;
        $this->transactionId = $transactionId;
        $this->timestamp = $timestamp;
    }

    public function setIsPending(){
        $this->isDone = false;
        $this->isPending = true;
    }

    public function setIsDone(){
        $this->isDone = true;
        $this->isPending = false;
    }
}

?>