<?php
namespace Routing;
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

?>