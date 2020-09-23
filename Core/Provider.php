<?php

namespace Core;
use Core\Logger;
use PDO;

abstract class Provider{
    public PDO $client;
    public ?Logger $logger;

    public function __construct(PDO $client, Logger $logger = null){
        $this->client = $client;
        $this->logger = $logger;

        foreach( $this->getCreationScript() as $script ) {
            $this->client->exec($script);
        }
    }

    public abstract function getCreationScript():array;
}
?>