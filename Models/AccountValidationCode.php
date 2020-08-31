<?php
namespace Models;
    
class AccountValidationCode{
    use LoadFromStd;
    public string $account;
    public string $code;
}
?>