<?php
namespace Models;
use stdClass;

trait LoadFromStd{

    public function load(stdClass $obj){
        foreach($obj as $key => $value){
            if(property_exists($this, $key)){
                if(is_object($value)){
                    if(get_class($value) !== get_class($this->$key)){
                        $cast = get_class($this->$key);
                        $instance = new $cast();
                        if(method_exists($instance,'load')){
                            $instance->load($value);
                        }
                        $this->$key = $instance;
                    }
                }
                else{
                    $this->$key = $value;
                }
            }
        }
    }

    static function staticLoad(stdClass $obj){
        
    }
}


?>