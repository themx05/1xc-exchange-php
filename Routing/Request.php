<?php
namespace Routing;

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

?>