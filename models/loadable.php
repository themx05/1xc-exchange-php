<?php
 namespace Models{

    trait LoadFromStd{

        public function load(stdClass $obj){
            foreach($obj as $key => $value){
                if(property_exists($this, $key)){
                    $this->$key = $value;
                }
            }
        }
    }
 }

?>