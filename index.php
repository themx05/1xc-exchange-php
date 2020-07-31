<?php
require_once('./bootstrap.php');

use Core\Logger;
use Core\MerchantProvider;
use Core\MethodProvider;
use Core\TicketProvider;
use Core\UserProvider;
use Routing\App;
use Routing\BodyParser;
use Routing\CorsConfiguration;
use Routing\Request;
use Routing\Response;

define("TEMPLATE_DIR","./templates");
define("CB_URL", "https://api.coinbase.com");

$_SERVER['REQUEST_URI'] = "/".$_GET['route'];

session_start();

$logger = new Logger(__DIR__."/error_log");

$client = getDatabaseInstance();

$application = new App();

$cors = new CorsConfiguration();
$cors->whiteListBasicMethods();
$cors->whiteListMethods('GET','POST','PUT','PATCH','DELETE');
$cors->whiteListOrigin("localhost", "http://192.168.43.153:3000", "http://localhost", "http://localhost:3000", "https://1xcrypto.net", "https://office.1xcrypto.net");
$cors->whiteListheaders("Content-Type", "X-TOKEN");

$application->setOption("home","../");

// Handle CORS Requests
$application->global($cors->createHandler());
// JSON content parsing middleware.
$application->global(BodyParser::json());
//Inject PDO instance
$application->global(function(Request& $request, Response& $response, Closure $next){
    global $client;
    $request->setOption('storage', $client);
    $next();
});
// Inject Connected User informations
$application->global(function(Request& $request, Response& $response, Closure $next){
    if(isConnected()){
        $request->setOption("connected", true);
        $request->setOption("isAdmin", isUserAnAdmin());
        $request->setOption("user", getUser());
    }
    else{
        $request->setOption("connected", false);
        $request->setOption('isAdmin', false);
    }
    $next();
});

$application->get("/logout", function (Request $req, Response $res){
    if($req->getOption('connected')){
        logUserOut();
    }
    
    $res->json([
        'success' => true
    ]);
});

includeDirectory("./routes");

$application->get("/patch",function (Request $req, Response $res){
    $client = $req->getOption('storage');

    if($client instanceof PDO){
        $methodProvider = new MethodProvider($client);
        $methods = $methodProvider->getMethods();
        $ticketProvider = new TicketProvider($client);
        $tickets = $ticketProvider->getTickets();

        foreach($methods as $method){
            foreach($tickets as $ticket){
                if($method['id'] === $ticket['source']['id']){
                    $initial_source = $ticket['source'];
                    $copy = $method;
                    $copy['details']= $initial_source['details'];
                    $copy['details']['percentage'] = $initial_source['percentage'];
                    $copy['details']['pattern'] = $method['pattern'];

                    if(isset($copy['addedAt'])){
                        unset($copy['addedAt']);
                    }
                    if(isset($copy['updatedAt'])){
                        unset($copy['updatedAt']);
                    }

                    $query = "UPDATE Tickets set source = ? WHERE id = ?";
                    $stmt = $client->prepare($query);
                    $stmt->execute([json_encode($copy), $ticket['id']]);
                }
                if($method['id'] === $ticket['dest']['id']){
                    $initial_source = $ticket['dest'];
                    $copy = $method;
                    $copy['details']= $initial_source['details'];
                    $copy['details']['percentage'] = $initial_source['percentage'];
                    $copy['details']['pattern'] = $method['pattern'];

                    if(isset($copy['addedAt'])){
                        unset($copy['addedAt']);
                    }
                    if(isset($copy['updatedAt'])){
                        unset($copy['updatedAt']);
                    }
                    
                    $query = "UPDATE Tickets set dest = ? WHERE id = ?";
                    $stmt = $client->prepare($query);
                    $stmt->execute([json_encode($copy), $ticket['id']]);
                }
            }
        }
    }
});

$application->global(function (Request $req, Response $res){
    $res->json(['success' => false]);
});

$application->handle();
?>