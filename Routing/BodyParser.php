<?php

namespace Routing{

    use Closure;

    class BodyParser{

        public static function json(): Closure{
            return function(Request& $request, Response $response, Closure $next){
                $match = array();
                if(preg_match("/^application\/json/i",$request->headers['content-type'], $match)){
                    $input = json_decode(file_get_contents("php://input"));
                    $request->setOption('body',$input);
                }
                $next();
            };
        }
    }
}
?>