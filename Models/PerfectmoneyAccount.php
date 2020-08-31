<?php
namespace Models;
class PerfectmoneyAccount{
    use LoadFromStd;

    public string $accountId;
    public string $passphrase;
    public string $alternatePassphrase;

}
?>