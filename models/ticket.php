<?php
namespace Models{
    class Ticket{
        use LoadFromStd;
        public string $id;
        public string $userId;
        public Method $source;
        public Method $dest;
        public double $amount;
        public double $rate;
        public string $address;
        public string $status;
        public bool $allowed;
        public bool $enableCommission;
        public int $emittedAt;
        public int $confirmedAt;
        public int $paidAt;
        public int $cancelledAt;

        public const STATUS_PENDING = "pending";
        public const STATUS_CONFIRMED = "confirmed";
        public const STATUS_CANCELLED = "cancelled";
        public const STATUS_PAID = "paid";
    }

    class ExpectedPayment extends Money{
        use LoadFromStd;
        public string $id;
        public string $ticketId;
        public string $type;
        public string $address;
        public string $paymentUrl;

    }
}
?>