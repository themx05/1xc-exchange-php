<?php

namespace Core;
use Core\Logger;

class ServiceClient{
    public ?Logger $logger;

    public function __construct(Logger $logger = null){
        $this->logger = $logger;
    }
}
?>