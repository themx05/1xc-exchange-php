<?php

use Core\ExpectedPaymentProvider;
use Core\MethodAccountProvider;
use Core\TicketProvider;
use Core\TransactionProvider;
use Core\UserProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$coinbaseWebHookRouter = new Router();

/*$coinbaseWebHookRouter->get("/:account/:transaction", function(Request $req, Response $res){
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
    
    $logger->info("Coinbase WebHook. Data: ".json_encode($event));
    $client->beginTransaction();

    if($event->type === "wallet:addresses:new-payment"){
        $logger->info("New payment WebHook received");
        /// It is a new payment
        /// Next step : Knowing the payment details, let's check if there is an expected payment on the used address.
        $transaction = $event->additional_data->transaction;

        $coinbaseUtils = new CoinbaseUtils($coinbase_account, $logger);

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

            $ticket = $ticketProvider->getTicketById($expectation['ticketId']);
            if(
                $real_transaction->type === "send" &&
                doubleval($real_transaction->amount->amount) >= doubleval($expectation['amount']) && 
                strtoupper($real_transaction->amount->currency) === $expectation['currency']
            ){
                $logger->info("Everything is okay. Creating confirmation data.");
                $confirmationData = new ConfirmationData(
                    generateHash(),
                    $real_transaction->amount->currency,
                    $expectation['id'],
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
                        "Transfert 1xCrypto",
                        json_decode(json_encode($ticket)),
                        $expectation['id'],
                        $client
                    );
                    /// Bind Country to gateway
                    if(isset($ticket['dest']['details']['country'])){
                        $gateway->setCountry($ticket['dest']['details']['country']);
                    }
                    $user = $userProvider->getProfileById($ticket['userId']);
                    if(isset($user)){
                        $gateway->setCustomer(json_decode(json_encode($user)));
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
                    if($expectationProvider->deleteExpectedPayment($expectation['id'])){
                        $logger->info("Everything goes fine.");
                        $client->commit();
                        return $res->json([
                            'success' => true
                        ]);
                    }
                }
            }
        }
        else{
            $pending_transaction = $transactionProvider->getTransactionByReference($real_transaction->id);
            if(isset($pending_transaction)){
                // There is a pending transaction
                $logger->info("A transaction in pending state was found.");
                if($real_transaction->type === "send" &&
                $real_transaction->status === "completed"){
                    if($client instanceof PDO){
                        $ticket = $ticketProvider->getTicketById($pending_transaction['ticketId']);
                        if(isset($ticket)){
                            $logger->info("Updating transaction");
                            $update_tx = $client->prepare("UPDATE Transactions set status = 'done', validationDate = NOW() WHERE id = ?");
                            if($update_tx->execute([$pending_transaction['id']])){
                                $logger->info("Updating ticket");
                                $update_ticket = $client->prepare("UPDATE Tickets SET status = 'paid', paidAt = NOW() WHERE id = ?");
                                if($update_ticket->execute([$ticket['id']])){
                                    $client->commit();
                                    return $res->json([
                                        'success' => true
                                    ]);
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
    return $res->json(['success' => false]);
});

global $application;
$application->router("/coinbase", $coinbaseWebHookRouter);
?>