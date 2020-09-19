<?php
namespace Routing;
class App extends Router{

    public function __construct(){
        $segment = $_SERVER['REQUEST_URI'];
        $method = $_SERVER['REQUEST_METHOD'];
        $lowercase_headers = [];
        foreach (getallheaders() as $header => $value) {
            $lowercase_headers[strtolower($header)] = $value;
        }
        parent::__construct(new Request($lowercase_headers, $segment, $method, [], $_GET), new Response());
        $this->route_segment = $segment;
    }
}
?>