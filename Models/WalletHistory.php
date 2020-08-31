<?php
namespace Models;
class WalletHistory{
    use LoadFromStd;

    public string $id;
    public string $type;
    public string $wallet;
    public string $memo;
    public int $creationDate;

    public const TYPE_COMMISSION = "commission";
    public const TYPE_NORMAL = "normal";
}
?>