<?php
namespace Models{
    class Wallet{
        use LoadFromStd;

        public string $id;
        public string $userId;
        public string $type;
        public Money $balance;
        public int $createdAt;

        public const TYPE_BUSINESS = "business";
        public const TYPE_STANDARD = "standard";
    }

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

}
?>