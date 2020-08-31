<?php
namespace Models;

class Money{
    use LoadFromStd;
    public string $currency;
    public float $amount;
}

?>