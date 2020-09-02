<?php
namespace Models{
    class SystemAdmin{
        use LoadFromStd;
        public string $id;
        public string $firstName;
        public string $lastName;
        public string $gender;
        public string $alias;
        public string $passwordHash;
        public int $createdAt;
        public int $updatedAt;
        public bool $isRoot = false;
    }
}
?>