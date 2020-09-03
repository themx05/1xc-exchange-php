<?php

use Core\MethodAccountProvider;
use Core\SystemProperties;
use Core\UserProvider;
use Core\WalletProvider;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Models\Method;
use Models\Wallet;
use Routing\Request;
use Routing\Response;
use Routing\Router;
use Utils\Utils;

$walletRouter = new Router();

$walletRouter->get("/confirm/:method/:userId", function(Request $req, Response $res){
    global $logger;
    $logger->info("Creating a business wallet after a confirmed payment is done.");
    $txId = $req->getQuery('id');
    $method = $req->getParam('method');
    $userId = $req->getParam('userId');

    if($method !== Method::CATEGORY_MOBILE){
        return $res->json(Utils::buildErrors());
    }
    
    $client = $req->getOption('storage');
    $systemProps = new SystemProperties($req->getOption('storage'));
    $methodAccountProvider = new MethodAccountProvider($req->getOption('storage'));
    $userProvider = new UserProvider($req->getOption('storage'));

    $fees = $systemProps->getBusinessWalletFee();
    $user = $userProvider->getProfileById($userId);
    if($client instanceof PDO && $user !== null && $user->isMerchant === 1){
        $logger->info("Well, user is a partner");
        if(isset($fees)){
            $logger->info("Registration fees are available. Fees: ".json_encode($fees));
            if($fees->amount > 0){
                $feda_account = $methodAccountProvider->getFedaPay();
                FedaPay::setEnvironment("live");
                FedaPay::setApiKey($feda_account->privateKey);

                $transaction = Transaction::retrieve($txId);
                if(
                    $transaction->status === "approved" && 
                    floatval($transaction->amount) === floatval($fees->amount) &&
                    $transaction->currency === $fees->currency
                ){
                    $logger->info("Transaction is okay.");
    
                    $walletProvider = new WalletProvider($client);
                    $wallet = $walletProvider->getBusinessWalletByUser($user->id);
                    if($wallet === null){
                        $logger->info("User didn't have a business wallet.");
                        $client->beginTransaction();
                        $walletId = $walletProvider->createWallet(Wallet::TYPE_BUSINESS,'XOF',0,$user->id);
                        if(!empty($walletId)){
                            $registrationId = $walletProvider->saveRegistrationFeeInstant(
                                $user->id,
                                $walletId,
                                Method::typeFromFedaMode($transaction->mode) ,
                                $txId,
                                $transaction->amount,
                                $fees->currency, 
                                time()
                            );
                            if(!empty($registrationId)){
                                $logger->info("Wallet created");
                                $client->commit();
                                return $res->redirect("https://1xcrypto.net/account/merchant");
                            }
                            $client->rollBack();
                        }
                    }
                }
            }
        }
    }

    return $res->status(403)->json(Utils::buildErrors());
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
        if($user !== null && $user->isMerchant){
            $logger->info("User is a partner");
            if($fees->amount === 0){ /// USER CAN ONLY CREATE A BUSINESS WALLET WHEN IT IS FREE
                $logger->info("Creation fees are 0.");
                $walletProvider = new WalletProvider($client);
                $wallet = $walletProvider->getBusinessWalletByUser($user->id);
                if($wallet === null){
                    $logger->info("User didn't have a wallet account");
                    $client->beginTransaction();

                    $walletId = $walletProvider->createWallet(Wallet::TYPE_BUSINESS,'XOF',0,$user->id);
                    if(!empty($walletId)){
                        $logger->info("Created business wallet");
                        $registrationId = $walletProvider->saveRegistrationFeeInstant(
                            $user->id,
                            $walletId,
                            "",
                            "",
                            0,
                            $fees->currency, 
                            time()
                        );
                        if(!empty($registrationId)){
                            $logger->info("Saved registration entry");
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
    return $res->status(403)->json(Utils::buildErrors());
});

$walletRouter->global(function(Request $req, Response $res, Closure $next){
    if($req->getOption('connected')){
        return $next();
    }
    return $res->json(Utils::buildErrors());
});

$walletRouter->get("/", function(Request $req, Response $res){
    $walletProvider = new WalletProvider($req->getOption('storage'));
    if($req->getOption('isAdmin')){
        $wallets = $walletProvider->getWallets();
        return $res->json(Utils::buildSuccess($wallets));
    }else{
        $wallets =  $walletProvider->getWalletsByUser($req->getOption('user')['id']);
        return $res->json(Utils::buildSuccess($wallets));
    }
    return $res->json(Utils::buildErrors());
});

$walletRouter->get("/fee", function(Request $req, Response $res){
    $systemProvider = new SystemProperties($req->getOption('storage'));
    return $res->json(Utils::buildSuccess($systemProvider->getBusinessWalletFee()));
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
        FedaPay::setApiKey($feda_account->privateKey);
        $fedaTrans = Transaction::create([
            'description' => "Business Wallet",
            'amount' => $fee->amount,
            'callback_url' => "https://api.1xcrypto.net/wallets/confirm/mobile/{$user->id}",
            'currency' => [
                'iso' => $fee->currency
            ],
            'customer' => [
                'firstname' => $user->firstName,
                'lastname' => $user->lastName,
                'email' => $user->email
            ]
        ]);
        $paymentUrl = $fedaTrans->generateToken()->url;
        return $res->json(Utils::buildSuccess($paymentUrl));
    }
    return $res->json(Utils::buildErrors());
});

$singleWallet = new Router();

$singleWallet->get("/",function(Request $req, Response $res){
    // Return wallets
    $walletProvider = new WalletProvider($req->getOption('storage'));
    $wallet = $walletProvider->getWalletById($req->getParam('wallet'));
    if($wallet !== null){
        return $res->json(Utils::buildSuccess($wallet));
    }
});

$singleWallet->get("/history", function(Request $req, Response $res){
    $walletId = $req->getParam('wallet');
    $walletProvider = new WalletProvider($req->getOption('storage'));
    $history = $walletProvider->getHistoriesByWallet($walletId);

    if(isset($history)){
        return $res->json(Utils::buildSuccess($history));
    }
    return $res->json(Utils::buildErrors());
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
                if($wallet !== null){
                    $client->beginTransaction();
                    $depositId = $walletProvider->deposit($walletId, $data->amount, $wallet->balance->currency,$data->memo);
                    if(isset($depositId)){
                        $client->commit();
                        return $res->json(Utils::buildSuccess($depositId));
                    }
                    $client->rollBack();
                }
            }
        }
    }
    return $res->json(Utils::buildErrors());
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
                if($wallet !== null){
                    if($wallet->canDebit($data->amount)){
                        $client->beginTransaction();
                        $depositId = $walletProvider->withdraw($walletId, $data->amount, $wallet->balance->currency,$data->memo);
                        if(isset($depositId)){
                            $client->commit();
                            return $res->json(Utils::buildSuccess($depositId));
                        }
                        $client->rollBack();
                    }
                }
            }
        }
    }
    return $res->json(Utils::buildErrors());
});

$walletRouter->router("/:wallet", $singleWallet);

global $application;
$application->router("/wallets", $walletRouter);
?>