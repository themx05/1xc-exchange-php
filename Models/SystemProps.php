<?php
namespace Models;
use stdClass;

class SystemProps{
    use LoadFromStd;

    public Money $businessAccountFee;
    public AuthenticationProps $authentication;
}
?>