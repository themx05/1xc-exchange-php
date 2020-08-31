<?php
namespace Routing;

class RouteUtils{
    public static function extractParams(string $pattern, string $test){
        $matches = array();
        $named = array();
        if(preg_match("/$pattern/A", $test ,$matches) === 1){
            foreach($matches as $key => $value){
                if($key != '0' && intval($key) === 0){
                    //Here are our named parameters !!!
                    $named[$key] = $value;
                }
            }
        }
        return $named;
    }
}

?>