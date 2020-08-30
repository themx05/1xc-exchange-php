<?php
namespace Models{
    class AmountLimitation{
        use LoadFromStd;
        public double $minAmount;
        public double $maxAmount;
        public double $staticFee;
        public double $percentage;
        public string $pattern;
    }

    class BankingDetails extends AmountLimitation{
        public string $currency;
        public string $account;
    }

    class MobileDetails extends AmountLimitation{
        public string $address;
        public string $currency;
        public string $country;
    }

    class TransferDetails extends AmountLimitation{
        public string $currency;
    }

    class CryptoCurrencyDetails extends AmountLimitation{
        
    }
}
?>