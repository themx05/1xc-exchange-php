<?php

namespace Routing{

use Closure;

class CorsConfiguration{
        public $allow_origins;
        public $allow_headers;
        public $allow_methods;
        public $allow_credentials;
        private $is_any_origin_allowed;

        public function __construct(){
            $this->allow_origins = [];
            $this->allow_headers = [];
            $this->allow_credentials = true;
            $this->allow_methods = [];
            $this->is_any_origin_allowed = false;
        }

        public function allowCredentials(bool $allow=true){
            $this->allow_credentials = $allow;
        }

        public function allowAnyOrigin(){
            $this->is_any_origin_allowed = true;
        }

        public function whiteListOrigin(string ...$origin){
            $this->is_any_origin_allowed = false;
            $this->allow_origins = array_merge($this->allow_origins, $origin);
        }

        public function isOriginAllowed(string $origin){
            return $this->is_any_origin_allowed || in_array($origin, $this->allow_origins);
        }

        public function whiteListMethods(string ...$method){
            $this->allow_methods = array_merge($this->allow_methods, $method);
        }

        public function whiteListBasicMethods(){
            return $this->whiteListMethods("GET","POST");
        }

        public function whiteListheaders(string ...$headers){
            $this->allow_headers = array_merge($this->allow_headers, $headers);
        }

        public function createHandler(): Closure{
            return function(Request& $request, Response& $response, Closure $next){
                if(isset($request->headers['Origin'])){
                    $origin = $request->headers['Origin'];
                    $method = $request->method;
    
                    if($this->isOriginAllowed($origin)){
                        $response->header("Access-Control-Allow-Origin", $origin);
                        $response->header("Access-Control-Allow-Headers", implode(", ", $this->allow_headers));
                        $response->header("Access-Control-Allow-Methods", implode(", ",$this->allow_methods));
                        $response->header("Access-Control-Allow-Credentials", $this->allow_credentials ? "true":"false");
                        $response->send("");
                    }
                }
                $next();
            };
        }
    }
}
?>