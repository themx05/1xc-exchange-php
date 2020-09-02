<?php

namespace Models;
class RegistrationFeeTransaction{
    use LoadFromStd;


    public string $id;
    public string $user;
    public string $wallet;
    public string $method;
    public string $reference;
    public int $paymentDate;
}

?>