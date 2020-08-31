<?php
namespace Models;

class User{
    use LoadFromStd;
    public string $id;
    public string $firstName;
    public string $lastName;
    public string $gender;
    public string $email;
    private bool $isMerchant;
    public string $passwordHash;

    public const GENDER_MALE = "male";
    public const GENDER_FEMALE = "female";
}
?>