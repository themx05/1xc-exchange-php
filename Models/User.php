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
    public bool $verified = false;
    public string $createdAt;
    public string $updatedAt;

    public const GENDER_MALE = "male";
    public const GENDER_FEMALE = "female";
    public const STATUS_ACTIVE = "active";
    public const STATUS_DISABLED = "disabled";
    
    public static function isGenderValid(string $gender){
        return in_array($gender, [static::GENDER_MALE, static::GENDER_FEMALE]);
    }
}
?>