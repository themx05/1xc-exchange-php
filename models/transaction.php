<?php
namespace Models{
    class Transaction extends Money{
        use LoadFromStd;
        public string $id;
        public string $ticketId;
        public string $variant;
        public string $type;
        public string $reference;
        public string $source;
        public string $dest;
        public int $createdAt;
        public int $timestamp;
        public string $status;


        public const VARIANT_IN = "in";
        public const VARIANT_OUT = "out";

        public const STATUS_PENDING = "pending";
        public const STATUS_DONE = "done";
    }
}
?>