<?php

use Core\ConfirmationData;
use Core\ExpectedPaymentProvider;
use Core\MethodAccountProvider;
use Core\PaymentGateway;
use Core\TicketProvider;
use Core\TransactionProvider;
use Core\UserProvider;
use Models\Ticket;
use Models\Transaction;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Coinbase\Coinbase;
use Utils\Utils;

$coinbaseWebHookRouter = new Router();
/**
FOR TESTING PURPOSES: Disabled in production.
    $coinbaseWebHookRouter->get("/:account/:transaction", function(Request $req, Response $res){
    global $logger;
    $txId = $req->getParam("transaction");
    $accountId  = $req->getParam("account");
    $client = $req->getOption('storage');

    $accountProvider = new MethodAccountProvider($client);
    $coinbase_account = $accountProvider->getCoinbase();
    $coinbaseUtils = new CoinbaseUtils($coinbase_account, $logger);
    $account = $coinbaseUtils->getAccount($accountId);
    $real_transaction = $coinbaseUtils->getTransaction($accountId, $txId);
    $logger->info("Coinbase Account is ".json_encode($account));
    $logger->info("Coinbase transaction is ".json_encode($real_transaction));
    
    return $res->json(['success' => true, 'transaction' => $real_transaction, 'account' => $account]);
});

$coinbaseWebHookRouter->get("/:tx", function(Request $req, Response $res){
    global $logger;
    $tx = $req->getParam('tx');
    $client = $req->getOption('storage');
    $accountProvider = new MethodAccountProvider($client);
    $feda = $accountProvider->getFedaPay();
    FedaPay::setEnvironment('live');
    FedaPay::setApiKey($feda['details']['privateKey']);
    $transaction = Transaction::retrieve($tx);
    return $res->json($transaction);
});*/

/**
 * Next Step: Handle Deposits from internal account
 */
$coinbaseWebHookRouter->post("/",function(Request $req, Response $res){
    global $logger;
    $client = $req->getOption('storage');
    $event = $req->getOption('body');

    $accountProvider = new MethodAccountProvider($client);
    $transactionProvider = new TransactionProvider($client);
    $ticketProvider = new TicketProvider($client);
    $expectationProvider = new ExpectedPaymentProvider($client);
    $userProvider = new UserProvider($client);

    $coinbase_account = $accountProvider->getCoinbase();
    if($coinbase_account !== null){
        $logger->info("Coinbase WebHook. Data: ".json_encode($event));
        $client->beginTransaction();
    
        if($event->type === "wallet:addresses:new-payment"){
            $logger->info("New payment WebHook received");
            /// It is a new payment
            /// Next step : Knowing the payment details, let's check if there is an expected payment on the used address.
            $transaction = $event->additional_data->transaction;
    
            $coinbaseUtils = new Coinbase($coinbase_account, $logger);
    
            $account = $coinbaseUtils->getAccount($event->account->id);
            $address = $coinbaseUtils->getAddress($account->id, $event->data->id);
            $real_transaction = $coinbaseUtils->getTransaction($account->id, $transaction->id);
    
            $expectation = $expectationProvider->getExpectedPaymentByAddress($address->address);
    
            $logger->info("Coinbase Account is ".json_encode($account));
            $logger->info("Coinbase Address is ".json_encode($address));
            $logger->info("Coinbase transaction is ".json_encode($real_transaction));
    
            if(isset($expectation)){
    
                $logger->info("An expected payment is found.");
                $logger->info("Expectation ".json_encode($expectation));
    
                $ticket = $ticketProvider->getTicketById($expectation->ticketId);
                if(
                    $ticket !== null &&
                    $real_transaction->type === "send" &&
                    doubleval($real_transaction->amount->amount) >= $expectation->amount && 
                    strtoupper($real_transaction->amount->currency) === $expectation->currency
                ){
                    $logger->info("Everything is okay. Creating confirmation data.");
                    $confirmationData = new ConfirmationData(
                        Utils::generateHash(),
                        $real_transaction->amount->currency,
                        $expectation->id,
                        $real_transaction->amount->amount,
                        $real_transaction->amount->currency,
                        "",
                        $address->address,
                        $real_transaction->id,
                        time()
                    );
    
                    $logger->info("Saving incoming transaction");
                    $in_tx = $transactionProvider->createInTicketTransaction($ticket,$confirmationData);
                    if(!empty($in_tx)){
                        $gateway = new PaymentGateway(
                            $ticket->getLabel(),
                            $ticket,
                            $expectation->id,
                            $client
                        );
                        /// Bind Country to gateway
                        if($ticket->dest->getCountry() !== null ){
                            $gateway->setCountry($ticket->dest->getCountry());
                        }
                        $user = $userProvider->getProfileById($ticket->userId);
                        if($user !== null){
                            $gateway->setCustomer($user);
                        }
                        // Process payment
                        try{
                            // Step 7 - 1
                            $logger->info("Processing payout");
                            $payment_result = $gateway->process();
                            if(isset($payment_result)){
                                $logger->info("Payout processed");
                                $out_tx = $transactionProvider->createOutTicketTransaction($ticket,$payment_result);
                            }
                        }
                        catch(Exception $e){
                            $logger->error($e->getMessage());
                        }
    
                        $logger->info("Deleting expected payment data.");
                        if($expectationProvider->deleteExpectedPayment($expectation->id)){
                            $logger->info("Everything goes fine.");
                            $client->commit();
                            return $res->json(Utils::buildSuccess(true));
                        }
                    }
                }
            }
            else{
                $pending_transaction = $transactionProvider->getTransactionByReference($real_transaction->id);
                if($pending_transaction !== null){
                    // There is a pending transaction
                    $logger->info("A transaction in pending state was found.");
                    if($real_transaction->type === "send" &&
                    $real_transaction->status === "completed"){
                        if($client instanceof PDO){
                            $ticket = $ticketProvider->getTicketById($pending_transaction->ticketId);
                            if($ticket !== null){
                                $logger->info("Updating transaction");
                                $pending_transaction->status = Transaction::STATUS_DONE;
                                $pending_transaction->validatedAt = time();
                                $update_tx = $client->prepare("UPDATE Transactions set data = ? WHERE id = ?");
                                if($update_tx->execute([json_encode($pending_transaction), $pending_transaction->id])){
                                    $logger->info("Updating ticket");
                                    $ticket->status = Ticket::STATUS_PAID;
                                    $ticket->paidAt = time();
                                    $update_ticket = $client->prepare("UPDATE Tickets SET data = ? WHERE id = ?");
                                    if($update_ticket->execute([json_encode($ticket), $ticket->id])){
                                        $client->commit();
                                        return $res->json(Utils::buildSuccess(true));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }   
    }

    $logger->info("Rolling back.");
    $client->rollBack();
    return $res->json(Utils::buildErrors());
});

global $application;
$application->router("/coinbase", $coinbaseWebHookRouter);
?>