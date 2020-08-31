<?php
namespace Models;
class AmountLimitation{
    use LoadFromStd;
    public float $minAmount;
    public float $maxAmount;
    public float $staticFee;
    public float $percentage;
    public string $pattern;
}
?>