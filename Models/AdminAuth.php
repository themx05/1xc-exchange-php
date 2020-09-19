<?php
namespace Models;

class AdminAuth{
    public string $userId;
    public string $firstName;
    public string $lastName;
    public array $roles;


    function hasRole(string $search){
        foreach ($this->roles as $pattern) {
            if(preg_match($pattern,$search)){
                return true;
            }
        }
        return false;
    }

    function hasRoles(string ...$roles){
        foreach ($roles as $subject) {
            if(!$this->hasRole($subject)){
                return false;
            }
        }
        return true;
    }
}
?>