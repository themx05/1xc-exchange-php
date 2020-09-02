<?php

namespace Core;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException as InvalidArgumentException;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger{
    public $log_file;

    public function __construct(string $file_path){
        if(!file_exists($file_path)){
            $file  = fopen($file_path,"a");
            fclose($file);
        }
        $this->log_file = $file_path;
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
        $full = "[$date][$level]: $message".PHP_EOL;
        file_put_contents($this->log_file, $full, FILE_APPEND);
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