<?php
namespace Routing;
class App extends Router{

    public function __construct(){
        $segment = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        $headers = getallheaders();
        parent::__construct(new Request($headers, $segment, $method, [], $_GET), new Response());
        $this->route_segment = $segment;
    }
}
?>