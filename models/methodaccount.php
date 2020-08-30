<?php
namespace Models{

    class OnlineApiAccount{
        use LoadFromStd;
        public string $publicKey;
        public string $privateKey;
    }

    class CoinbaseAccount extends OnlineApiAccount{

    }

    class FedapayAccount extends OnlineApiAccount{

    }

    class PerfectMoneyAccount{
        use LoadFromStd;

        public string $accountId;
        public string $passphrase;
        public string $alternatePassphrase;

    }

    class MethodAccount{
        use LoadFromStd;
        public string $id;
        public string $type;
        public stdClass $details;

        public const TYPE_PERFECT = "perfectmoney";
        public const TYPE_COINBASE = "coinbase";
        public const TYPE_FEDAPAY = "fedapay";
    }
}
?>