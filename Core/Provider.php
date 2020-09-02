<?php

namespace Core;
use Core\Logger;
use PDO;

class Provider{
    public PDO $client;
    public ?Logger $logger;

    public function __construct(PDO $client, Logger $logger = null){
        $this->client = $client;
        $this->logger = $logger;
    }
}
?>