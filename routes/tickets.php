<?php
use Core\ExpectedPaymentProvider;
use Core\MethodProvider;
use Core\TicketProvider;
use Core\TransactionProvider;
use Core\UserProvider;
use Providers\WalletProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$ticketRouter = new Router();

$ticketRouter->global(function (Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    else{
        return $res->json([
            'success' => false,
            'requireAuth' => true
        ]);
    }
});

$ticketRouter->get("/", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $tickets = [];
    if($req->getOption('isAdmin')){
        $tickets = $ticketProvider->getTickets();
    }
    else{
        $tickets = $ticketProvider->getTicketsByUser($req->getOption('user')['id']);
    }

    if(isset($tickets)){
        return $res->json([
            'success' => true,
            'tickets' => $tickets
        ]);
    }
    return $res->json(['success' => false]);
});

$ticketRouter->post("/",function (Request $req, Response $res){
    global $logger;
    $data = $req->getOption('body');
    $logger->info("Data is ".json_encode($data));
    $client = $req->getOption('storage');
    if($client instanceof PDO){
        $userProvider = new UserProvider($client);
        $methodProvider = new MethodProvider($client);
        $ticketProvider = new TicketProvider($client);
        $expectationProvider = new ExpectedPaymentProvider($client);
        $walletProvider = new WalletProvider($client);
        $client->beginTransaction();
        
        $user = $userProvider->getProfileById($req->getOption('user')['id']);

        if(!isset($user)){
            $client->rollBack();
            return $res->json(['success' => false]);
        }
        if(
            isset($data->source) && 
            isset($data->dest) && 
            $data->source !== $data->dest && 
            isset($data->amount) && 
            $data->amount > 0 &&
            isset($data->address)
        ){
            /**
             * Steps 
             * 1 - Retrieve Stored User
             * 2 - Retrieve source and destination payment methods from store
             * 3 - get international exchange rate 
             * 4 - check validity again
             * 5 - store ticket 
             * 6 - generate and save payment expectation and payment links (if applicable);
             */

            $source = $methodProvider->getMethodById($data->source);
            $destination = $methodProvider->getMethodById($data->dest);

            $source_currency = PaymentGateway::getCurrencyFromMethod(json_decode(json_encode($source)));
            $dest_currency = PaymentGateway::getCurrencyFromMethod(json_decode(json_encode($destination)));

            $rate = -1;

            if($source_currency !== $dest_currency){
                $conversionService = new ConversionProvider();
                $rate = $conversionService->convert([
                    'source' => $source_currency,
                    'dest' => $dest_currency,
                    'amount' => 1
                ]);   
            }else{
                $rate = [
                    'rate' => 1
                ];
            }

            if($rate !== -1){
                // step 3
                if(
                    isset($rate['rate'])
                ){
                    // step 4 
                    $ticket = [];
                    $ticket['userId'] = $user['id'];
                    $ticket['source'] = $source;
                    $ticket['dest'] = $destination;
                    $ticket['rate'] = $source_currency === $dest_currency ? 1 : $rate['rate'];
                    $ticket['amount'] = $data->amount;
                    $ticket['address'] = $data->address;
                    $ticket['status'] = "pending";
                    $ticket['enableCommission'] = 0 ;
                    $ticket['allowed'] = in_array($destination['type'], ["moovmoney"]) ? 0:1;

                    $wallet = $walletProvider->getWalletByUser($user['id']);
                    if(isset($wallet) && $wallet['type'] === "business"){
                        $logger->info("Commission is enabled on this ticket");
                        $ticket['enableCommission'] = intval($user['isMerchant']) ;
                    }
                    //step 5
                    $ticket_done = $ticketProvider->createTicket($ticket);
                    if(!empty($ticket_done)){
                        $ticket = $ticketProvider->getTicketById($ticket_done);
                        $paymentId = $expectationProvider->createExpectedPayment($user,$ticket);
                        if(!empty($paymentId)){
                            //Step 6
                            $payment = $expectationProvider->getExpectedPaymentByTicketId($ticket_done);
                            if(isset($payment)){
                                $logger->info("Expectation saved");
                                $client->commit();
                                return $res->json([
                                    'success' => true,
                                    'ticketId' => $ticket_done,
                                    'approved' => $ticket['approved'],
                                    'paymentId' => $paymentId
                                ]);
                            }else{
                                $logger->error("Failed to create payment expectation");
                            }
                        }
                        else{
                            $logger->error("Failed to create payment details");
                        }
                    }
                    else{
                        $logger->error("Failed to create the ticket itself.");
                    }
                }
                else{
                    $logger->error("Failed to proceed to currency conversion");
                }
            }
            else{
                $logger->error("Failed to proceed to currency conversion");
            }
        }
        $logger->info("Failed to create ticket");
        $client->rollBack();
    }
    return $res->json(['success' => false]);
});

$singleTicket = new Router();
$singleTicket->get("/", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticket = $ticketProvider->getTicketById($req->getParam('ticket'));
    if( isset($ticket) && ($req->getOption('isAdmin') || $req->getOption('user')['id'] === $ticket['userId'])){
        return $res->json([
            'success' => true,
            'ticket' => $ticket
        ]);
    }
    return $res->json(['success' => false]);
});

$singleTicket->middleware("/:action",function (Request $req, Response $res, Closure $next){
    $action = $req->getParam('action');
    if($req->getOption('isAdmin')){
        if(in_array($action, ["confirm","abort","allow","autopay","manual-pay"])){
            return $next();
        }
        else{
            return $res->json(['success' => false, 'message' => "bad action"]);
        }
    }
    else{
        if(in_array($action, ["abort"])){
            return $next();
        }
    }
    return $res->json(['success' => false, 'requireAuth' => true]);
});

$singleTicket->get("/allow", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $done = $ticketProvider->approveTicket($ticketId);
    if($done){
        return $res->json(['success' => true]);
    }
    return $res->json(['success' => false]);
});

$singleTicket->get("/abort", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $done = $ticketProvider->abortTicket($ticketId);
    if($done){
        return $res->json(['success' => true]);
    }
    return $res->json(['success' => false]);
});

$singleTicket->get("/autopay", function(Request $req, Response $res){
    global $logger;
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $logger->info("Automatic payment launched for ticket ".$ticketId);
    $ticket = $ticketProvider->getTicketById($ticketId);

    if(isset($ticket) && $ticket['status'] === "confirmed" && $ticket['allowed'] == 1){
        $logger->info("There is a match for the given ticket. Launching Payment Gateway.");
        $gateway = new PaymentGateway(
            "Transfert 1xCrypto",
            json_decode(json_encode($ticket)),
            "",
            $req->getOption('storage')
        );

        $userProvider = new UserProvider($req->getOption('storage'));
        $customer = $userProvider->getProfileById($ticket['userId']);

        $gateway->setCustomer(json_decode(json_encode($customer)));
        /// Bind Country to gateway
        if(isset($ticket['dest']['details']['country'])){
            $gateway->setCountry($ticket['dest']['details']['country']);
        }

        $logger->info("Launching processing");
        $payment_result = $gateway->process();

        if(isset($payment_result)){
            $logger->info("Payment is done");
            $pdo = $req->getOption('storage');
            if($pdo instanceof PDO){
                $pdo->beginTransaction();
                $logger->info("Storing transaction");
                $transactionProvider = new TransactionProvider($pdo);
                $transactionId = $transactionProvider->createOutTicketTransaction($ticket, $payment_result);
                if(intval($ticket['enableCommission']) === 1){
                    $walletProvider = new WalletProvider($req->getOption('storage'));
                    $wallet = $walletProvider->getWalletByUser($ticket['userId']);
                    if(isset($wallet)){
                        $ticket_obj = json_decode(json_encode($ticket));
                        $emitterBonus = PaymentGateway::calculateEmitterBonus(json_decode(json_encode($ticket)));
                        $currency = PaymentGateway::getCurrencyFromMethod($ticket_obj->dest);
                        $history = $walletProvider->deposit($wallet['id'], $emitterBonus, $currency ,"Commission sur Ticket {$ticket['id']}", WalletProvider::TX_COMMISSION);
                        if(empty($history)){
                            $pdo->rollBack();
                            return $res->json(['success' => false, 'message' => "Failed to deposit commission"]);
                        }
                    }
                }
                if(!empty($transactionId)){
                    $logger->info("Stored transaction");
                    $pdo->commit();
                    return $res->json([
                        'success' => true,
                        'done' => $payment_result->isDone,
                        'pending' => $payment_result->isPending
                    ]);
                }
                $logger->info("Transaction not stored. Rolling back ");
                $pdo->rollBack();
            }
        }
    }
    $logger->info("Things went wrong.");
    return $res->json(['success' => false]);
});

$singleTicket->post("/manual-pay", function(Request $req, Response $res){
    $data = $req->getOption('body');
    $ticketId = $req->getParam('ticket');
    $amount = floatval($data->amount);
    $source = $data->source;
    $reference = $data->reference;

    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticket = $ticketProvider->getTicketById($ticketId);

    if(empty($source)){
        return $res->json(['success' => false, 'message' => 'You didn\'t specify the source of the payment']);
    }

    if(!isset($amount) || $amount < PaymentGateway::extractFees(json_decode(json_encode($ticket)))){
        return $res->json(['success' => false, 'message' => 'The amount you paid should be equal to the amount to be paid']);
    }

    if(empty($source)){
        return $res->json(['success' => false, 'message' => 'You didn\'t specify the reference of the payment']);
    }

    if(isset($ticket) && $ticket['status'] === "confirmed" && intval($ticket['allowed']) === 1){
        
        $userProvider = new UserProvider($req->getOption('storage'));
        $customer = $userProvider->getProfileById($ticket['userId']);

        $payment_result = new ConfirmationData(
            generateHash(),
            $ticket['dest']['type'],
            "",
            $amount,
            PaymentGateway::getCurrencyFromMethod(
                json_decode(json_encode($ticket['dest']))
            ),
            $source,
            $ticket['address'],
            $reference,
            time()
        );
        
        $pdo = $req->getOption('storage');
        if($pdo instanceof PDO){
            $pdo->beginTransaction();
            $transactionProvider = new TransactionProvider($pdo);
            $transactionId = $transactionProvider->createOutTicketTransaction($ticket, $payment_result);
            if(intval($ticket['enableCommission']) === 1){
                $walletProvider = new WalletProvider($pdo);
                $wallet = $walletProvider->getWalletByUser($ticket['userId']);
                if(isset($wallet)){
                    $ticket_obj = json_decode(json_encode($ticket));
                    $emitterBonus = PaymentGateway::calculateEmitterBonus(json_decode(json_encode($ticket)));
                    $currency = PaymentGateway::getCurrencyFromMethod($ticket_obj->dest);
                    $history = $walletProvider->deposit($wallet['id'], $emitterBonus, $currency ,"Commission sur Ticket {$ticket['id']}", WalletProvider::TX_COMMISSION);
                    if(empty($history)){
                        $pdo->rollBack();
                        return $res->json(['success' => false, 'message' => "Failed to deposit commission"]);
                    }
                }
            }
            if(!empty($transactionId)){
                $pdo->commit();
                return $res->json([
                    'success' => true,
                    'done' => $payment_result->isDone,
                    'pending' => $payment_result->isPending
                ]);
            }
            $pdo->rollBack();
        }
    }
    
    return $res->json(['success' => false]);

});

$ticketRouter->router("/:ticket", $singleTicket);

global $application;
$application->router("/tickets", $ticketRouter);
?>