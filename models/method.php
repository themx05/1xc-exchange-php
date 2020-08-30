<?php
namespace Models{

    class Method{
        use LoadFromStd;
        public string $id;
        public string $category;
        public string $type;
        public string $label;
        public string $icon;
        public bool $allowBuy;
        public bool $allowSell;
        public int $addedAt;
        public int $updatedAt;
        public stdClass $details;

        public const CATEGORY_BANKING = "banking";
        public const CATEGORY_MOBILE = "mobile";
        public const CATEGORY_TRANSFER = "transfer";
        public const CATEGORY_CRYPTO = "cryptocurrency";

    }
}

?>