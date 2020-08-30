<?php
namespace Models{

    class AuthenticationProps{
        use LoadFromStd;
        public string $secret;
    }

    class SystemProps{
        public Money $businessAccountFee;
        public AuthenticationProps $authentication;
    }
}

?>