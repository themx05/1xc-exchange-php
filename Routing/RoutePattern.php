<?php
namespace Routing;

use Closure;

/**
 * Defines common properties of a specified portion of route.
 * It registers it own handlers to handle the suite of the request.
 */
class RoutePattern{
    private $method;
    public $pattern;
    private $handler;

    public function __construct(string $method, string $pattern){
        $this->method = $method;
        $this->pattern = $pattern;
    }

    public function hasMatch(string $request_test, string $request_method){
        if(is_callable($this->handler)){
            return $this->closureHasMatch($request_test, $request_method);
        }else{
            return $this->routerHasMatch($request_test, $request_method);
        }
    }

    /**
     * Check it the whole tested route match the defined pattern
     */
    private function closureHasMatch(string $request_test, string $method){
        $matches = array();
        $method_matches  = array();
        return (preg_match("/^{$this->pattern}$/", $request_test,$matches) === 1) && (preg_match($this->method, $method, $method_matches) === 1);
    }

    /**
     * Check if the firt segment of the route matches the defined pattern
     */
    private function routerHasMatch(string $request_test, string $method){
        $chunks = preg_split("/\//",$request_test);
        $splitted = "/";
        $count = count($chunks);

        if($count >= 2 && isset($chunks[1])){
            $splitted .=$chunks[1];
        }

        $matches = array();
        $method_matches  = array();
        return (preg_match("/^{$this->pattern}$/", $splitted,$matches) === 1) && (preg_match($this->method, $method, $method_matches) === 1);
    }

    public function setRouter(Router $router){
        $this->handler = $router;
    }

    public function setCallable(Closure $callable){
        $this->handler = $callable;
    }

    public function getCallable(): Closure{
        return $this->handler;
    }

    public function getRouter(): Router{
        return $this->handler;
    }

    public function getPattern(): string{
        return $this->pattern;
    }

    public function getHandler(){
        if(is_callable($this->handler)){
            return $this->getCallable();
        }else{
            return $this->getRouter();
        }
    }
}
?>