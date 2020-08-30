<?php

use Core\MethodAccountProvider;
use Core\SystemProperties;
use Core\UserProvider;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Providers\WalletProvider;
use Routing\Request;
use Routing\Response;
use Routing\Router;

$walletRouter = new Router();

$walletRouter->get("/confirm/:method/:userId", function(Request $req, Response $res){
    global $logger;
    $logger->info("Creating a business wallet after a confirmed payment is done.");
    $txId = $req->getQuery('id');
    $method = $req->getParam('method');
    $userId = $req->getParam('userId');

    if($method !=="mobile"){
        return $res->json(buildErrors());
    }
    
    $client = $req->getOption('storage');
    $systemProps = new SystemProperties($req->getOption('storage'));
    $methodAccountProvider = new MethodAccountProvider($req->getOption('storage'));
    $userProvider = new UserProvider($req->getOption('storage'));

    $fees = $systemProps->getBusinessWalletFee();
    $user = $userProvider->getProfileById($userId);
    if($client instanceof PDO && isset($user) && intval($user['isMerchant']) === 1){
        $logger->info("Well, user is a partner");
        if(isset($fees)){
            $logger->info("Registration fees are available. Fees: ".json_encode($fees));
            if($fees->amount > 0){
                $feda_account = $methodAccountProvider->getFedaPay();
                FedaPay::setEnvironment("live");
                FedaPay::setApiKey($feda_account['details']['privateKey']);

                $transaction = Transaction::retrieve($txId);
                if(
                    $transaction->status === "approved" && 
                    floatval($transaction->amount) === floatval($fees->amount) &&
                    $transaction->currency === $fees->currency
                ){
                    $logger->info("Transaction is okay.");
    
                    $walletProvider = new WalletProvider($client);
                    $wallet = $walletProvider->getWalletByUser($user['id']);
                    if(!isset($wallet) || !isset($wallet['id'])){
                        $logger->info("User didn't have a business wallet.");
                        $client->beginTransaction();
                        $registrationId = $walletProvider->saveRegistrationFeeInstant(
                            $user['id'],
                            $transaction->mode,
                            $txId,
                            $transaction->amount,
                            $fees->currency, 
                            time()
                        );
                        $logger->info("Registration entry saved.");
                        if(!empty($registrationId)){
                            $logger->info("Creating wallet");
                            $walletId = $walletProvider->createWallet(WalletProvider::WALLET_BUSINESS,'XOF',0,$user['id']);
                            if(!empty($walletId)){
                                $logger->info("Wallet created");
                                $client->commit();
                                return $res->redirect("https://1xcrypto.net/account/merchant");
                            }
                        }
                        $client->rollBack();
                    }
                }
            }
        }
    }

    return $res->status(403)->json(buildErrors());
});

$walletRouter->get("/create/:userId", function(Request $req, Response $res){
    global $logger;
    $logger->info("Trying to create a free business wallet for a user");

    $client = $req->getOption('storage');

    $systemProps = new SystemProperties($client);
    $fees = $systemProps->getBusinessWalletFee();
    $userProvider = new UserProvider($client);
    $user = $userProvider->getProfileById($req->getParam('userId'));

    if($client instanceof PDO){
        if(isset($user) && intval($user['isMerchant']) === 1){
            $logger->info("User is a partner");
            if($fees->amount === 0){ /// YOU CAN ONLY CREATE A BUSINESS ACCOUNT WHEN IT IS FREE
                $logger->info("Creation fees are 0.");
                $walletProvider = new WalletProvider($client);
                $wallet = $walletProvider->getWalletByUser($user['id']);
                if(!isset($wallet) || !isset($wallet['id'])){
                    $logger->info("User didn't have a wallet account");
                    $client->beginTransaction();
                    $registrationId = $walletProvider->saveRegistrationFeeInstant(
                        $user['id'],
                        "",
                        "",
                        0,
                        $fees->currency, 
                        time()
                    );
                    if(!empty($registrationId)){
                        $logger->info("Saved registration entry");
                        $walletId = $walletProvider->createWallet(WalletProvider::WALLET_BUSINESS,'XOF',0,$user['id']);
                        if(!empty($walletId)){
                            $logger->info("Created business wallet");
                            $client->commit();
                            return $res->redirect("https://1xcrypto.net/account/merchant");
                        }
                    }
                    $logger->error("Could not create wallet");
                    $client->rollBack();
                }
            }
        }
    }
    return $res->status(403)->json(buildErrors());
});

$walletRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    return $res->json(buildErrors());
});

$walletRouter->get("/", function(Request $req, Response $res){
    $walletProvider = new WalletProvider($req->getOption('storage'));
    if($req->getOption('isAdmin')){
        $wallets =  $walletProvider->getWallets();
        if(isset($wallets)){
            return $res->json(buildSuccess($wallets));
        }
    }else{
        $wallet =  $walletProvider->getWalletByUser($req->getOption('user')['id']);
        if(isset($wallet)){
            return $res->json(buildSuccess($wallet));
        }
    }
    return $res->json(buildErrors());
});

$walletRouter->get("/fee", function(Request $req, Response $res){
    $systemProvider = new SystemProperties($req->getOption('storage'));
    return $res->json(buildSuccess($systemProvider->getBusinessWalletFee()));
});

$walletRouter->get("/payment-link", function(Request $req, Response $res){
    $systemProvider = new SystemProperties($req->getOption('storage'));
    $userProvider = new UserProvider(($req->getOption('storage')));
    $connected_user = $req->getOption('user');
    $user = $userProvider->getProfileById($connected_user['id']);
    $fee = $systemProvider->getBusinessWalletFee();

    if(isset($fee)){
        // Create fedapay link and launch payment.
        $methodAccountProvider = new MethodAccountProvider($req->getOption('storage'));
        $feda_account = $methodAccountProvider->getFedaPay();
        FedaPay::setEnvironment("live");
        FedaPay::setApiKey($feda_account['details']['privateKey']);
        $fedaTrans = Transaction::create([
            'description' => "Frais de portefeuille business",
            'amount' => $fee->amount,
            'callback_url' => "https://api.1xcrypto.net/wallets/confirm/mobile/{$user['id']}",
            'currency' => [
                'iso' => $fee->currency
            ],
            'customer' => [
                'firstname' => $user['firstName'],
                'lastname' => $user['lastName'],
                'email' => $user['email']
            ]
        ]);
        $paymentUrl = $fedaTrans->generateToken()->url;
        return $res->json(buildSuccess($paymentUrl));
    }
    return $res->json(buildErrors());
});

$singleWallet = new Router();

$singleWallet->get("/",function(Request $req, Response $res){
    // Return wallets
    $walletProvider = new WalletProvider($req->getOption('storage'));
    $wallet = $walletProvider->getWalletById($req->getParam('wallet'));
    return $res->json(buildSuccess($wallet));
});

$singleWallet->get("/history", function(Request $req, Response $res){
    $walletId = $req->getParam('wallet');
    $walletProvider = new WalletProvider($req->getOption('storage'));
    $history = $walletProvider->getHistoriesByWallet($walletId);

    if(isset($history)){
        return $res->json(buildSuccess($history));
    }
    return $res->json(buildErrors());
    /// Get wallet history
});

$singleWallet->post("/credit", function(Request $req, Response $res){
    if($req->getOption('isAdmin')){
        $data = $req->getOption('body');
        if(isset($data->memo) && isset($data->amount) && $data->amount > 0){
            $walletProvider = new WalletProvider($req->getOption('storage'));
            $walletId = $req->getParam('wallet');

            $client = $req->getOption('storage');
            if($client instanceof PDO){
                $wallet = $walletProvider->getWalletById($walletId);
                if(isset($wallet)){
                    $client->beginTransaction();
                    $depositId = $walletProvider->deposit($walletId, $data->amount, $wallet['balance']['currency'],$data->memo);
                    if(isset($depositId)){
                        $client->commit();
                        return $res->json(buildSuccess($depositId));
                    }
                    $client->rollBack();
                }
            }
        }
    }
    return $res->json(buildErrors());
});

$singleWallet->post("/debit", function(Request $req, Response $res){
    if($req->getOption('isAdmin')){
        $data = $req->getOption('body');
        if(isset($data->memo) && isset($data->amount) && $data->amount > 0){
            $walletProvider = new WalletProvider($req->getOption('storage'));
            $walletId = $req->getParam('wallet');

            $client = $req->getOption('storage');
            if($client instanceof PDO){
                $wallet = $walletProvider->getWalletById($walletId);
                if(isset($wallet)){
                    if($data->amount <= $wallet['balance']['amount']){
                        $client->beginTransaction();
                        $depositId = $walletProvider->withdraw($walletId, $data->amount, $wallet['balance']['currency'],$data->memo);
                        if(isset($depositId)){
                            $client->commit();
                            return $res->json(buildSuccess($depositId));
                        }
                        $client->rollBack();
                    }
                }
            }
        }
    }
    return $res->json(buildErrors());
});

$walletRouter->router("/:wallet", $singleWallet);

global $application;
$application->router("/wallets", $walletRouter);
?>