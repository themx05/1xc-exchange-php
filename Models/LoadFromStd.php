<?php
namespace Models;
use stdClass;

trait LoadFromStd{

    public function __construct(stdClass $obj = null){
        if($obj !== null){
            $this->load($obj);
        }
    }

    public function load(stdClass $obj){
        foreach($obj as $key => $value){
            if(property_exists($this, $key)){
                $this->$key = $value;
            }
        }
    }
}


?>