<?php

/**
 * Author Maximilien Comlan <maximiliencomlan05@gmail.com>
 * Github: themx05
 */
/**
 * Defines classes and methods that will be used to handle user requests.
 */
namespace Routing{

use Closure;

/**
     * Defines common properties of a given request.
     * It exposes accessors and mutators to access/bind parameters to the request.
     */
    class Request{
        /**
         * The original path of the request reteieved from REQUEST_URI
         */
        public $path;

        /**
         * Request parameters
         */

        public $headers;

        /**
         * HTTP method used for this request.
         */
        public $method;

        /**
         * Route parameters 
         */
        private $params;

        /**
         * GET parameters 
         */
        private $query;

        /**
         * Optional parameters passed to the request object
         */
        private $optionals;

        public function __construct(array $headers = [],string $path, string $method, array $params, array $query){
            $this->path = $path;
            $this->method = $method;
            $this->params = $params;
            $this->query = $query;
            $this->optionals = [];
            $this->headers = $headers;
        }

        public function getQuery(string $key){
            return $this->query[$key];
        }

        public function getParam(string $key){
            return $this->params[$key];
        }

        public function setParam(string $key, $value){
            $this->params[$key] = $value;
        }

        public function getOption(string $key){
            return $this->optionals[$key];
        }

        public function setOption(string $key, $value){
            $this->optionals[$key] = $value;
        }
    }

    /**
     * Defines common methods to send a response to user.
     */
    class Response{
        private $_headers;
        private $_status;

        public function __construct(){
            $this->_headers = [];
            $this->_status = 200;
        }

        public function status(int $code){
            $this->_status = $code;
            return $this;
        }

        public function header($tag, $value){
            $this->_headers[$tag] = $value;
            return $this;
        }

        private function sendHeaders(){
            http_response_code($this->_status);
            foreach ($this->_headers as $key => $value) {
                header("{$key}:{$value}");
            }
        }

        public function send($res){
            $this->sendHeaders();
            echo $res;
        }

        public function json($res){
            $this->header("Content-Type", "application/json;charset=utf-8");
            $this->send(json_encode($res));
        }

        public function text(string $text){
            $this->header("Content-Type", "text/plain;charset=utf-8");
            $this->send($text);
        }

        public function file(string $filename){
            if(file_exists($filename)){
                $this->header("Content-Disposition", "attachment; filename=".basename($filename));
                $this->header("Content-Type", mime_content_type($filename));
                $this->header('Content-Length', filesize($filename));
                $this->header('Expires', '0');
                $this->header('Cache-Control', 'must-revalidate');

                $this->sendHeaders();
                ob_clean();
                flush();
                readfile($filename);
                exit;
            }
        }

        public function redirect(string $to){
            $this->header('Location', $to);
            $this->sendHeaders();
            exit;
        }
    }

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

    /**
     * Defines common methods and properties of the request handlers.
     */
    interface RequestHandler{
        public function setNext(callable $handler);
        public function handle();
    }

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

    class App extends Router{

        public function __construct(){
            $segment = $_SERVER['REQUEST_URI'];
            $method = $_SERVER['REQUEST_METHOD'];
            $headers = getallheaders();
            parent::__construct(new Request($headers, $segment, $method, [], $_GET), new Response());
            $this->route_segment = $segment;
        }
    }
}

?>