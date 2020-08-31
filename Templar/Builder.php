<?php
    namespace Templar;
    class Builder {
        protected $file;
        protected $tags = array();
        
        public function __construct($file,$lang) {
            $this->file = $file;
			$this->lang = $lang;
        }

        public function set(string $key, $value) {
            $this->tags[$key] = $value;
        }

        public function render() {
            if (!file_exists($this->file)) {
            	return "Template doesn't exist ($this->file).<br />";
            }
            $raw = file_get_contents($this->file);
            
            foreach ($this->tags as $key => $value) {
				$replacer = "[@$key]";
				$raw = str_replace($replacer, $value, $raw);
            }
			
            return $raw;
        }

        static public function merge($templates, $separator = "\n") {
            $output = "";
            
            foreach ($templates as $template) {
            	$content = (get_class($template) !== "StringBuilder")
            		? "Error, incorrect type - expected Template."
            		: $template->render();
            	$output .= $content . $separator;
            }
            
            return $output;
        }
    }

?>