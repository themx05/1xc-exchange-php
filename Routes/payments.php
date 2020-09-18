<?php

use Core\ConfirmationData;
use Core\ExpectedPaymentProvider;
use Core\PaymentGateway;
use Core\TicketProvider;
use Core\TransactionProvider;
use Core\UserProvider;
use Core\Utils;
use Core\WalletProvider;
use Models\Method;
use Models\WalletHistory;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils as UtilsUtils;

$paymentRouter = new Router();

$paymentRouter->middleware("/confirm/:method/:paymentId", function(Request $req, Response $res){
    global $logger;
    $method = $req->getParam('method');
    $paymentId = $req->getParam('paymentId');

    $client = $req->getOption('storage');
    $client->beginTransaction();
    $paymentProvider = new ExpectedPaymentProvider($client);
    $ticketProvider = new TicketProvider($client);
    $transactionProvider = new TransactionProvider($client);
    $userProvider = new UserProvider($client);

    $expectedPayment = $paymentProvider->getExpectedPaymentById($paymentId);
    if($expectedPayment !== null){
        $ticket = $ticketProvider->getTicketById($expectedPayment->ticketId);
        /**
         * Step 1 : Prepare confirmationdata
         * Step 2 : Retrieve expected payment data 
         * Step 3 : Retrieve Ticket Details
         * Step 4 : Check infomations
         * Step 5 : Store transaction details,
         * Step 6 : mark ticket as confirmed
         * Step 7-1 : Make Payment if there is enough money to automate payment
         * Step 7-2 : Mark transaction details 
         * Step 7-3 : Mark Ticket as paid 
         * Step 8 : Remove Entry from Expected Payments
         * Step 9 : Send ok message depending on the message 
         */
        if($client instanceof PDO){
            $logger->info("Payment method is $method");
            //Step 1
            $confirmation_data = null;
            if($method === Method::TYPE_PERFECTMONEY){
                $confirmation_data = Utils:: preparePMTransaction($client);
            }
            else if($method === Method::CATEGORY_MOBILE){
                $logger->info("User paid through FedaPay gateway");
                $confirmation_data = Utils::prepareFedaPayTransaction($client,$req->getQuery('id'),$paymentId);
            }
            else if($method === Method::TYPE_INTERNAL){
                $logger->info("User wants to pay with internal wallet.");
                // Handle Internal Payment. User must specifiy wallet id 
                // To Pay through internal wallet user MUST BE connected. To ensure that the user definetely approved this payment by himself.
                if($req->getOption('connected') && !$req->getOption('isAdmin')){
                    $cn_user = $userProvider->getProfileById($req->getOption('user')['id']);
                    $walletId = $req->getQuery('wallet');
                    $walletProvider = new WalletProvider($req->getOption('storage'));
                    $wallet = $walletProvider->getWalletById($walletId);
    
                    if($cn_user !== null){
                        if($wallet !== null){
                            if($wallet->userId === $cn_user->id){
                                $logger->info("Yes. the user really owns this wallet. Processing withdrawal");
                                $history = $walletProvider->withdraw($wallet->id,$expectedPayment->amount, $expectedPayment->currency,"Ticket {$ticket->id}");
                                if(!empty($history)){
                                    $logger->info("Withdrawal processed");
                                    $confirmation_data = new ConfirmationData(
                                        UtilsUtils::generateHash(),
                                        $method,
                                        $paymentId,
                                        $expectedPayment->amount,
                                        $expectedPayment->currency,
                                        $wallet->id,
                                        "",
                                        $history,
                                        time()
                                    );
                                }
                                else{
                                    $logger->error("Failed to withdraw the specified amount");
                                    $client->rollBack();
                                    return $res->json(UtilsUtils::buildErrors());
                                }
                            }
                            else{
                                $logger->error("The user wanted to fake this request. He doesn't own this wallet.");
                                $client->rollBack();
                                return $res->json(UtilsUtils::buildErrors());
                            }
                        }
                        else{
                            $logger->error("The user wanted to fake this request. The wallet he specified is invalid.");
                            $client->rollBack();
                            return $res->json(UtilsUtils::buildErrors());
                        }
                    }
                    else{
                        $client->rollBack();
                        return $res->json(UtilsUtils::buildErrors());
                    }
                }
                else{
                    $logger->error("The user wanted to fake this request. His details doesn't anymore exist in our system.");
                    $client->rollBack();
                    return $res->json(UtilsUtils::buildErrors());
                }
            }
    
            if(isset($confirmation_data)){
                if($expectedPayment !== null){
                    $logger->info("There is an expected payment. Id: {$expectedPayment->id}");
                    if($ticket !== null){
                        $user = $userProvider->getProfileById($ticket->userId);
                        // Step 4
                        if(
                            doubleval($expectedPayment->amount) <= doubleval($confirmation_data->amount) &&
                            $expectedPayment->id === $confirmation_data->paymentId
                        ){
                            $logger->info("Amount is compatible");
                            $in_tx = $transactionProvider->createInTicketTransaction($ticket,$confirmation_data);
    
                            if(!empty($in_tx)){
                                $logger->info("Income saved in transactions. ID: $in_tx");
                                //Step 7 - What if there was no account stored and available to handle this transfer ?
                                $gateway = new PaymentGateway(
                                    $ticket->getLabel(),
                                    $ticket,
                                    $expectedPayment->id,
                                    $client
                                );
                                /// Bind Country to gateway
                                if($ticket->dest->getCountry() !== null ){
                                    $gateway->setCountry($ticket->dest->getCountry());
                                }
    
                                $logger->info("Processing payout.");
                                $result = $gateway->process();
                                if(isset($result)){
                                    $logger->info("Payout processed.");
                                    $logger->info("Saving payout transaction.");
                                    $out_tx = $transactionProvider->createOutTicketTransaction($ticket, $result);
                                    if(!empty($out_tx)){
                                        if($ticket->enableCommission){
                                            $logger->info("Commission should be deposed to merchant's business wallet");
                                            $walletProvider = new WalletProvider($logger);
                                            $wallet = $walletProvider->getBusinessWalletByUser($ticket->userId);
                                            if($wallet !== null){
                                                $emitterBonus = $ticket->getEmitterCommission();
                                                $currency = $ticket->dest->getCurrency();
                                                $history = $walletProvider->deposit($wallet->id, $emitterBonus, $currency ,"Commission {$ticket->getLabel()}", WalletHistory::TYPE_COMMISSION);
                                                if(empty($history)){
                                                    $client->rollBack();
                                                    $logger->error("Failed to deposit commission");
                                                    return $res->json(UtilsUtils::buildErrors(["Echec de depot de la commission"]));
                                                }
                                            }
                                        }
                                        $logger->info("Payout tansaction saved.");
                                    }
                                }
                                $logger->info("Deleting Previously used expected payment");
                                $done = $paymentProvider->deleteExpectedPayment($expectedPayment->id);
                                if($done){
                                    $logger->info("Expected Payment entry deleted. Committing transaction.");
                                    $client->commit();
                                    // Step 9
                                    if($method === Method::CATEGORY_MOBILE){
                                        $logger->info("Redirected user to its activity page.");
                                        return $res->redirect('https://1xcrypto.net/account/activity');
                                    }else{
                                        return $res->status(200)->json(UtilsUtils::buildSuccess(true));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $client->rollBack();

    }
    return $res->json(UtilsUtils::buildErrors());
});

$paymentRouter->get("/:ticket", function(Request $req, Response $res){
    $ticketId = $req->getParam('ticket');
    $expectationProvider = new ExpectedPaymentProvider($req->getOption('storage'));
    $payment = $expectationProvider->getExpectedPaymentByTicketId($ticketId);

    if($payment !== null){
        return $res->json(UtilsUtils::buildSuccess($payment));
    }
    return $res->json(UtilsUtils::buildErrors());
});

global $application;
$application->router("/payments", $paymentRouter);
?>