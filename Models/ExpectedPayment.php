<?php
namespace Models;
class ExpectedPayment extends Money{
    public string $id;
    public string $ticketId;
    public string $type;
    public string $address;
    public string $paymentUrl;

}

?>