<?php

namespace Core;

use CoinbaseUtils;
use FedaPay\FedaPay;
use FedaPay\Payout;
use FedaPay\Transaction;
use Core\WalletProvider;
use Exception;
use Models\Method;
use Models\Ticket;
use Models\User;
use PDO;
use SendTransaction;
use stdClass;
use Utils\Coinbase\Coinbase;
use Utils\Coinbase\SendTransaction as CoinbaseSendTransaction;
use Utils\Utils;

class PaymentGateway {
    /**
     * The defined payment method. either BTC or PerfectMoney or Mobile Money or whatever else
     */
    public string $method;

    /**
     * The payment source. for perfect money it is the account address 
     */
    public string $from;

    /**
     * The  address to which the defined amount should be transferred to
     */
    public string $to;

    /**
     * The amount to transfer
     */
    public float $amount;

    /**
     * The currency defined for amount to transfer
     */
    public string $currency;

    /**
     * a description provided (if applicable) to the payment
     */
    public string $memo;

    /**
     * The  ticket for which the payment is been done
     */
    public Ticket $ticket;

    /**
     * The customer data associated to this payout
     */
    public User $customer;

    /**
     * Country code of customer's given number (if method is mtn mobile money)
     */
    public string $country;

    /**
     * The id of the payment data made by the customer, which launched out this transaction
     */
    public string $expectedPayment;

    /**
     * The database instance passed. 
     */

    public PDO $database;

    public function __construct(
        string $memo,
        Ticket $ticket,
        string $expectedPayment, PDO $database = null){
        
        $this->method = $ticket->dest->type;
        $this->from = static::addressFromMethod($ticket->dest);
        $this->to = $ticket->address;
        $this->amount = static::extractFees($ticket);
        $this->currency = static::getCurrencyFromMethod($ticket->dest);
        $this->memo = $memo;
        $this->ticket = $ticket;
        $this->expectedPayment = $expectedPayment;
        $this->database = $database;
    }
    
    public function setCountry(string $code){
        $this->country = $code;
    }

    public function setCustomer(User $custom){
        $this->customer = $custom;
    }

    public function process(){
        global $logger;
        if($this->method === Method::TYPE_INTERNAL){
            return $this->processInternal();
        }
        else if($this->method === Method::TYPE_PERFECTMONEY){
            return $this->processPerfectMoney();
        }
        else if($this->method === "BTC" || $this->method === "ETH"){
            $logger->info("Dispatching process to coinbase handler. Method: ".$this->method);
            return $this->processCryptocurrency();
        }
        else if($this->method === Method::TYPE_MTN){
            return $this->processFedaPayMobile();
        }
        else{
            return null;
        }
    }

    private function processInternal(){
        global $logger;
        $provider = new WalletProvider($logger);
        $result = $provider->deposit($this->to,$this->amount, $this->currency, $this->memo);
        if(isset($result) && !empty($result)){
            // Transfer to internal account is done.
            // Let's return confirmation data
            $confirmation = new ConfirmationData(
                Utils::generateHash(),
                $this->method,
                $this->expectedPayment,
                $this->amount,
                $this->currency,
                "",
                $this->to,
                $result,
                time()
            );
            $logger->info("Deposit done on Internal wallet ".$this->to);
            return $confirmation;
        }
        return null;
    }

    private function processCryptocurrency(){
        global $logger;
        $methodAccountProvider = new MethodAccountProvider($logger);
        $coinbase_account = $methodAccountProvider->getCoinbase();

        $coinbaseUtils = new Coinbase($coinbase_account, $logger);

        $compatible_accounts = $coinbaseUtils->getWalletsByCurrency(strtoupper($this->currency));

        if(!empty($compatible_accounts)){
            $transaction = new CoinbaseSendTransaction(
                $this->to,
                $this->amount,
                $this->currency,
                $this->memo
            );
            foreach($compatible_accounts as $k => $wallet){
                try{
                    $balance = doubleval($wallet->balance->amount);
                    if(static::canSendAmount(
                        $balance,
                        $this->amount,
                        static::calculateSendingFees($this->ticket)
                    )){ 
                        /// We found the right account. Lets initiate transaction
                        $logger->info("Amount ".$this->amount." is ready to be sent from ".$wallet->id);
                        $transaction->idem = $this->ticket->id; /// to handle network errors

                        $logger->info("Transaction data: ".$transaction->__toString());
                        $result = $coinbaseUtils->createAccountSend($wallet->id, $transaction);

                        if(!isset($result)){
                            return null;
                        }
                        $confirmation = new ConfirmationData(
                            Utils::generateHash(),
                            $this->currency,
                            $this->expectedPayment,
                            $transaction->amount,
                            $transaction->currency,
                            $wallet->id,
                            $this->to,
                            $result->id,
                            time()
                        );

                        /*if($result->status === "pending"){
                            $confirmation->setIsPending();
                        }*/

                        return $confirmation;
                    }
                }
                catch(Exception $e){
                    $logger->error("Erreur d'envoi coinbase: ".$e->getMessage());
                    $logger->info("SendTransaction instance: ".json_encode($transaction));
                    return null;
                }
            }
        }
        return null;
    }

    private function processPerfectMoney(){
        global $logger;
        $methodAccountProvider = new MethodAccountProvider($logger);
        $pm_account = $methodAccountProvider->getPerfectMoney();
        $pmAccount = new PerfectMoney($pm_account->accountId, $pm_account->passphrase);
        $balance = $pmAccount->getBalance($this->ticket->dest->detailsAsBanking()->account);

        //Check if there is enough money to initiate payment
        if($balance > 0 && static::canSendAmount($balance, $this->amount, static::calculateSendingFees($this->ticket))){
            $spend = new PerfectMoneySpend(
                $this->ticket->dest->detailsAsBanking()->account,
                $this->ticket->address,
                $this->amount,
                $this->expectedPayment,
                $this->memo
            );

            $spend_result = $pmAccount->spend($spend); // Initiation du transfert;

            if(isset($spend_result)){
                return $spend_result;
            }
        }
        
        return null;
    }

    private  function processFedaPayMobile(){
        global $logger;
        try{
            $methodAccountProvider = new MethodAccountProvider($logger);
            $feda = $methodAccountProvider->getFedaPay();

            FedaPay::setEnvironment('live');
            FedaPay::setApiKey($feda->privateKey);

            // Les transactions automatiques ne sont actuellement supportées que pour MTN MOBILE MONEY
            if($this->method !== Method::TYPE_MTN){
                return null;
            }

            $amount_to_send = floor($this->amount/5) * 5; /// En CFA les montant tels que 1 franc, 2 francs sont exclus. les unites sont comptes par bond de 5 franc. donc il faut arrondir le montant vers l'entier, multiple de 5 le plus proche par defaut du montant originel.
            $fedaPayout = Payout::create([
                'amount' => $amount_to_send,
                'mode' => 'mtn',
                'currency' => [
                    'iso' => $this->currency,
                ],
                'customer' => [
                    'email' => $this->customer->email,
                    'firstname' => $this->customer->firstName,
                    'lastname' => $this->customer->lastName,
                    'phone_number' => [
                        'number' => $this->to,
                        'country' => strtolower($this->country)
                    ]
                ]
            ]);

            $fedaPayout->sendNow();

            if($fedaPayout->status === "sent"){
                $confirmation = new ConfirmationData(
                    Utils::generateHash(),
                    Method::TYPE_MTN,
                    $this->expectedPayment,
                    $fedaPayout->amount,
                    $this->currency,
                    $this->from,
                    $this->to,
                    $fedaPayout->reference,
                    $fedaPayout->sent_at
                );

                return $confirmation;
            }
        }
        catch(Exception $e){
            $logger->error($e->getMessage());
            return null;
        }

        return null;
    }

    public static function canSendAmount(float $balance, float $amount, float $sendingFee): bool{
        return $balance >= ($amount + $sendingFee);
    }

    public static function calculateSendingFees(Ticket $ticket){
        return $ticket->getSendingFees();
    }

    public static function calculateEmitterBonus(Ticket $ticket){
        return $ticket->getEmitterCommission();
    }

    public static function calculateFees(Ticket $ticket){
        return $ticket->totalFees();
    }

    /**
     * Used to extract fees from default amount
     */
    public static function extractFees(Ticket $ticket){
        return $ticket->amountWithoutFees();
    }

    public static function getCurrencyFromMethod(Method $method){
        return $method->getCurrency();
    }

    public static function addressFromMethod(Method $method){
        return $method->getAddress();
    }
}

?>