<?php

namespace Routing{

    use Closure;

    class BodyParser{

        public static function json(): Closure{
            return function(Request& $request, Response $response, Closure $next){
                $match = array();
                if(isset($request->headers['Content-Type'])){
                    if(preg_match("/^application\/json/i",$request->headers['Content-Type'], $match)){
                        $input = json_decode(file_get_contents("php://input"));
                        $request->setOption('body',$input);
                    }
                }
                $next();
            };
        }
    }
}
?>