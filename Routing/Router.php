<?php

/**
 * Author Maximilien Comlan <maximiliencomlan05@gmail.com>
 * Github: themx05
 */
/**
 * Defines classes and methods that will be used to handle user requests.
 */
namespace Routing;

use Closure;

class Router implements RequestHandler{

    public $request;
    public $response;
    public $route_segment;
    private $route_stack;
    private $down_passed_next_handler;
    private $next_route_walk;

    public function __construct(Request $request = null, Response $response = null){
        $this->request = $request;
        $this->response = $response;
        $this->route_stack = array();
        $this->next_route_walk = 0;
        $this->route_segment = "";
    }

    public function setNext(callable $handler){
        $this->down_passed_next_handler = $handler;
    }

    public function setOption(string $key,$value){
        $this->request->setOption($key, $value);
    }

    private function parsePattern(string $pattern): string{
        $pattern = preg_replace("/\//","\/",$pattern);
        $pattern = preg_replace("/:([a-zA-Z0-9]*)/", "(?<$1>[a-zA-Z0-9\-\.]*)",$pattern);
        return $pattern;
    }

    public function middleware(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(.*)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function global(Closure $callback): void{
        $routePattern = new RoutePattern("/^(.*)$/",$this->parsePattern("(.*)"));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function router(string $pattern, Router $router){
        $routePattern = new RoutePattern("/^(.*)$/",$this->parsePattern($pattern));
        $routePattern->setRouter($router);
        array_push($this->route_stack, $routePattern);
    }

    public function get(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(GET|get)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function post(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(POST|post)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function put(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(PUT|put)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function patch(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(PATCH|patch)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function options(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(OPTIONS|options)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function delete(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(DELETE|delete)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function head(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(HEAD|head)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    public function connect(string $pattern, Closure $callback): void{
        $routePattern = new RoutePattern("/^(CONNECT|connect)$/",$this->parsePattern($pattern));
        $routePattern->setCallable($callback);
        array_push($this->route_stack, $routePattern);
    }

    /**
     * Called to handle an incoming request.
     * it 
     *  - finds the first suitable registered pattern,
     *  - Creates a handler,
     *  - finds out the most suitable next route pattern,
     *  - Create a NextFunction class instance with the next suitable handler,
     *  - Binds the next suitable handler to the first handler 
     *  - Call the first handler.
     * 
     */

    public function handle(){
        $suitable_route_found = false;
        $stack_length = count($this->route_stack);
        if($this->next_route_walk >= $stack_length){
            // Nothing to do. No more routes to test.
            $nextHandler = $this->down_passed_next_handler;
            if(isset($nextHandler) && is_callable($nextHandler)){
                $nextHandler();
                return;
            }
            return;
        }
        for($i = $this->next_route_walk; $i < $stack_length; $i++){
            $pattern = $this->route_stack[$i];
            if($pattern->hasMatch($this->route_segment,$this->request->method)){
                /**
                 * We found the most suitable route.
                 * We need to build up the next function, 
                 * by recursively calling this function to find the next most suitable route.
                 */
                $suitable_route_found = true;
                $this->next_route_walk = $i + 1;

                $nextFunction = function(){
                    call_user_func_array([$this,'handle'],[]);
                };

                if($pattern instanceof RoutePattern){
                    /**
                     * Extracts route parameters into request.
                     */
                    $params = RouteUtils::extractParams($pattern->pattern,$this->route_segment);
                    foreach($params as $key => $value){
                        $this->request->setParam($key, $value);
                    }
                    $handler = $pattern->getHandler();
                    if(is_callable($handler)){
                        $handler($this->request, $this->response, $nextFunction);
                        return;
                    }

                    else if($handler instanceof Router){
                        /**
                         * The defined handler is a Router.
                         * We need to calculate the next route segment 
                         * by substracting the matching route of this handler 
                         * from the route segment registered in this Router.
                         */
                        $matches = array();
                        $exp = "/{$pattern->pattern}\/(?<nested>.*)/A";
                        $nextSegment = "/";
                        if(preg_match($exp,$this->route_segment,$matches) === 1){
                            /**
                            * There is valid segment that should match a nested segment. 
                            */
                            $nextSegment = "/".$matches['nested'];
                        }

                        $handler->request = $this->request;
                        $handler->response = $this->response;
                        $handler->route_segment = $nextSegment;

                        //Now, initiate sub router call.
                        $handler->handle();
                        return;
                    }
                }
            }
        }

        /**
         * No suitable route has been found. Has the parent caller passed a next function ? let's use it.
         */
        if(!$suitable_route_found){
            $nextHandler = $this->down_passed_next_handler;
            if(isset($nextHandler) && is_callable($nextHandler)){
                $nextHandler();
                return;
            }
        }
    }
}
?>