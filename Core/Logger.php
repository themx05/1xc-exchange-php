<?php

namespace Core;

use Predis\Client;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException as InvalidArgumentException;
use Psr\Log\LogLevel;
use Utils\Config;

class Logger extends AbstractLogger{
    public Client $redisClient;
    public string $channel;

    public function __construct(Client $client, string $channel){
        $this->redisClient = $client;
        $this->channel = $channel;
    }

    public function log($level, $message, array $context = array()){
        if(in_array($level, [
            LogLevel::ALERT, 
            LogLevel::CRITICAL, 
            LogLevel::DEBUG, 
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING
        ])){
            $this->writeToFile($level, $this->interpolate($message, $context));
        }
        else{
            throw new InvalidArgumentException();
        }
    }

    private function writeToFile(string $level, string $message){
        $date = date('d-M-Y H:i:s');
        $to_log = [
            'service' => Config::metadata()->name,
            'logLevel' => $level,
            'timestamp' => $date,
            'message' => $message
        ];
        $this->redisClient->publish($this->channel, json_encode($to_log));
    }

    public function interpolate(string $message, array $context = array()){
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}

?>