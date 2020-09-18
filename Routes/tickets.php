<?php

use Core\ConfirmationData;
use Core\ConversionService;
use Core\ExpectedPaymentProvider;
use Core\MethodProvider;
use Core\PaymentGateway;
use Core\TicketProvider;
use Core\TransactionProvider;
use Core\UserProvider;
use Core\WalletProvider;
use Models\ExchangeRate;
use Models\Method;
use Models\Ticket;
use Models\WalletHistory;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$ticketRouter = new Router();

$ticketRouter->global(function (Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    else{
        return $res->json(Utils::buildErrors([],['requireAuth'=>true]));
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
        return $res->json(Utils::buildSuccess($tickets));
    }
    return $res->json(Utils::buildErrors());
});

$ticketRouter->post("/",function (Request $req, Response $res){
    global $logger;
    $data = $req->getOption('body');
    $logger->info("Data is ".json_encode($data));
    $client = $req->getOption('storage');
    if($client instanceof PDO){
        $userProvider = new UserProvider($logger);
        $methodProvider = new MethodProvider($logger);
        $ticketProvider = new TicketProvider($client);
        $expectationProvider = new ExpectedPaymentProvider($client);
        $walletProvider = new WalletProvider($logger);
        $client->beginTransaction();
        
        $user = $userProvider->getProfileById($req->getOption('user')['id']);

        if(!isset($user)){
            $client->rollBack();
            return $res->json(Utils::buildErrors());
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

            $source_currency = $source->getCurrency();
            $dest_currency = $destination->getCurrency();

            if($source_currency !== $dest_currency){
                $conversionService = new ConversionService($logger);
                $rate = $conversionService->convert($source_currency, $dest_currency);   
            }else{
                $rate = new ExchangeRate();
                $rate->source = $source_currency;
                $rate->dest = $dest_currency;
                $rate->rate = 1;
                $rate->converted = 1;
                $rate->amount = 1;
            }

            if($rate !== null){
                // step 3
                if(
                    isset($rate->rate)
                ){
                    // step 4 
                    $ticket = [];
                    $ticket['userId'] = $user->id;
                    $ticket['source'] = $source;
                    $ticket['dest'] = $destination;
                    $ticket['rate'] = $source_currency === $dest_currency ? 1 : $rate->rate;
                    $ticket['amount'] = $data->amount;
                    $ticket['address'] = $data->address;
                    $ticket['status'] = Ticket::STATUS_PENDING;
                    $ticket['enableCommission'] = false ;
                    $ticket['allowed'] = in_array($destination->type, [Method::TYPE_MOOV]) ? false:true;

                    $wallet = $walletProvider->getBusinessWalletByUser($user->id);
                    if($wallet !== null){
                        $logger->info("Commission is enabled on this ticket");
                        $ticket['enableCommission'] = $user->isMerchant === true ;
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
                                return $res->json(Utils::buildSuccess([
                                    'ticketId' => $ticket_done,
                                    'approved' => $ticket->allowed,
                                    'paymentId' => $paymentId
                                ]));
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
    return $res->json(Utils::buildErrors());
});

$singleTicket = new Router();
$singleTicket->get("/", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticket = $ticketProvider->getTicketById($req->getParam('ticket'));
    if( $ticket!== null && ($req->getOption('isAdmin') || $req->getOption('user')['id'] === $ticket->userId)){
        return $res->json(Utils::buildSuccess($ticket));
    }
    return $res->json(Utils::buildErrors());
});

$singleTicket->middleware("/:action",function (Request $req, Response $res, Closure $next){
    $action = $req->getParam('action');
    if($req->getOption('isAdmin')){
        if(in_array($action, ["confirm","abort","allow","autopay","manual-pay"])){
            return $next();
        }
        else{
            return $res->json(Utils::buildErrors(['bad action']));
        }
    }
    else{
        if(in_array($action, ["abort"])){
            return $next();
        }
    }
    return $res->json(Utils::buildErrors([],['requireAuth' => true]));
});

$singleTicket->get("/allow", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $done = $ticketProvider->approveTicket($ticketId);
    if($done){
        return $res->json(Utils::buildSuccess());
    }
    return $res->json(Utils::buildErrors());
});

$singleTicket->get("/abort", function(Request $req, Response $res){
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $done = $ticketProvider->abortTicket($ticketId);
    if($done){
        return $res->json(Utils::buildSuccess());
    }
    return $res->json(Utils::buildErrors());
});

$singleTicket->get("/autopay", function(Request $req, Response $res){
    global $logger;
    $logger->info("Launching Automatic payment");
    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticketId = $req->getParam('ticket');
    $logger->info("Automatic payment launched for ticket ".$ticketId);
    $ticket = $ticketProvider->getTicketById($ticketId);

    if($ticket !== null && $ticket->isConfirmed() && $ticket->allowed){
        $logger->info("There is a match for the given ticket. Launching Payment Gateway.");
        $gateway = new PaymentGateway(
            $ticket->getLabel(),
            $ticket,
            "",
            $req->getOption('storage')
        );

        $userProvider = new UserProvider($logger);
        $customer = $userProvider->getProfileById($ticket->userId);

        $gateway->setCustomer($customer);
        /// Bind Country to gateway
        if($ticket->dest->getCountry() !== null){
            $gateway->setCountry($ticket->dest->getCountry());
        }

        $logger->info("Launching payout.");
        $payment_result = $gateway->process();

        if(isset($payment_result)){
            $logger->info("Payment is done");
            $pdo = $req->getOption('storage');
            if($pdo instanceof PDO){
                $pdo->beginTransaction();
                $logger->info("Saving transaction");
                $transactionProvider = new TransactionProvider($pdo);
                $transactionId = $transactionProvider->createOutTicketTransaction($ticket, $payment_result);
                if($ticket->enableCommission){
                    $walletProvider = new WalletProvider($req->getOption('storage'));
                    $wallet = $walletProvider->getBusinessWalletByUser($ticket->userId);
                    if($wallet !== null){
                        $ticket_obj = json_decode(json_encode($ticket));
                        $emitterBonus = $ticket->getEmitterCommission();
                        $currency = $ticket->dest->getCurrency();
                        $history = $walletProvider->deposit($wallet->id, $emitterBonus, $currency ,"Ticket {$ticket->id}", WalletHistory::TYPE_COMMISSION);
                        if(empty($history)){
                            $pdo->rollBack();
                            return $res->json(Utils::buildErrors(["Failed to deposit commission"]));
                        }
                    }
                }
                if(!empty($transactionId)){
                    $logger->info("Stored transaction");
                    $pdo->commit();
                    return $res->json(Utils::buildSuccess([
                        'done' => $payment_result->isDone,
                        'pending' => $payment_result->isPending
                    ]));
                }
                $logger->info("Transaction not stored. Rolling back ");
                $pdo->rollBack();
            }
        }
    }
    $logger->info("Things went wrong.");
    return $res->json(Utils::buildErrors());
});

$singleTicket->post("/manual-pay", function(Request $req, Response $res){
    global $logger;
    $data = $req->getOption('body');
    $ticketId = $req->getParam('ticket');
    $amount = floatval($data->amount);
    $source = $data->source;
    $reference = $data->reference;

    $ticketProvider = new TicketProvider($req->getOption('storage'));
    $ticket = $ticketProvider->getTicketById($ticketId);

    if(empty($source)){
        return $res->json(Utils::buildErrors(['source' => 'You didn\'t specify the source of the payment']));
    }

    if(!isset($amount) || $amount < $ticket->amountWithoutFees()){
        return $res->json(Utils::buildErrors(['amount' => 'The amount you paid should be equal to the amount to be paid']));
    }

    if(empty($source)){
        return $res->json(Utils::buildErrors(['source' => 'You didn\'t specify the reference of the payment']));
    }

    if($ticket !== null && $ticket->isConfirmed() && $ticket->allowed){
        
        $userProvider = new UserProvider($logger);
        $customer = $userProvider->getProfileById($ticket->userId);

        $payment_result = new ConfirmationData(
            Utils::generateHash(),
            $ticket->dest->type,
            "",
            $amount,
            $ticket->dest->getCurrency(),
            $source,
            $ticket->address,
            $reference,
            time()
        );
        
        $pdo = $req->getOption('storage');
        if($pdo instanceof PDO){
            $pdo->beginTransaction();
            $transactionProvider = new TransactionProvider($pdo);
            $transactionId = $transactionProvider->createOutTicketTransaction($ticket, $payment_result);
            if($ticket->enableCommission){
                $walletProvider = new WalletProvider($logger);
                $wallet = $walletProvider->getBusinessWalletByUser($ticket->userId);
                if($wallet !== null){
                    $emitterBonus = $ticket->getEmitterCommission();
                    $currency = $ticket->dest->getCurrency();
                    $history = $walletProvider->deposit($wallet->id, $emitterBonus, $currency ,"Ticket {$ticket->id}", WalletHistory::TYPE_COMMISSION);
                    if(empty($history)){
                        $pdo->rollBack();
                        return $res->json(Utils::buildErrors(["Failed to deposit commission"]));
                    }
                }
            }
            if(!empty($transactionId)){
                $pdo->commit();
                return $res->json(Utils::buildSuccess([
                    'done' => $payment_result->isDone,
                    'pending' => $payment_result->isPending
                ]));
            }
            $pdo->rollBack();
        }
    }
    return $res->json(Utils::buildErrors());
});

$ticketRouter->router("/:ticket", $singleTicket);

global $application;
$application->router("/tickets", $ticketRouter);
?>