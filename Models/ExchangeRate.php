<?php
namespace Models;

class ExchangeRate{
    use LoadFromStd;
    public string $source;
    public string $dest;
    public float $rate;
    public float $amount;
    public float $converted;
}
?>