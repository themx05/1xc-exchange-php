<?php
namespace Models;

use Predis\Client;

class Publisher{
    public Client $redis;
    private string $channel;

    public function __construct(Client $client, string $channel){
        $this->redis = $client;
        $this->channel = $channel;    
    }

    public function publish($event){
        $this->redis->publish($this->channel, json_encode($event));
    }
}

?>