<?php
namespace Models;
class BusinessProfile{
    use LoadFromStd;

    public string $id;
    public string $name;
    public string $userId;
    public string $country;
    public string $city;
    public string $phone;
    public string $email;
    public array $documents;
    public int $creationDate;
    public int $verificationDate;
    public string $status;

    public const STATUS_PENDING = "pending";
    public const STATUS_VERIFIED = "verified";

    public function __construct()
    {
        $this->documents = [];
    }
}
?>