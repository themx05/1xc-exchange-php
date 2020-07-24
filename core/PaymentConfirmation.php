<?php

use Core\MethodAccountProvider;
use FedaPay\FedaPay;
use FedaPay\Payout;
use FedaPay\Transaction;
use Providers\WalletProvider;

class ConfirmationData {
    public $id;
    public $type;
    public $paymentId;
    public $amount;
    public $units;
    public $source;
    public $destination;
    public $transactionId;
    public $timestamp;

    public $isDone = true;
    public $isPending  = false;
    
    public function __construct(
        string $id,
        string $type, 
        string $paymentId, 
        float $amount, 
        string $units, 
        string $source, 
        string $destination, 
        string $transactionId, 
        int $timestamp
    ){
        $this->id = $id;
        $this->type = $type;
        $this->paymentId = $paymentId;
        $this->amount = $amount;
        $this->units = $units;
        $this->source = $source;
        $this->destination = $destination;
        $this->transactionId = $transactionId;
        $this->timestamp = $timestamp;
    }

    public function setIsPending(){
        $this->isDone = false;
        $this->isPending = true;
    }

    public function setIsDone(){
        $this->isDone = true;
        $this->isPending = false;
    }
}

function preparePMTransaction(PDO $client){
    if(
        isset($_POST['PAYMENT_ID']) &&
        isset($_POST['PAYEE_ACCOUNT']) &&
        isset($_POST['PAYMENT_AMOUNT']) &&
        isset($_POST['PAYMENT_UNITS']) &&
        isset($_POST['PAYMENT_BATCH_NUM']) &&
        isset($_POST['PAYER_ACCOUNT']) &&
        isset($_POST['TIMESTAMPGMT']) && 
        isset($_POST['V2_HASH'])
    ){
        $v2_hash = $_POST['V2_HASH'];
        $paymentId = protectString($_POST['PAYMENT_ID']);

        $pm_account_stmt = $client->prepare("SELECT * FROM MethodAccount WHERE type = ?");
        
        if($pm_account_stmt->execute(['perfectmoney']) && $pm_account_stmt->rowCount() > 0){
            $raw_pm = $pm_account_stmt->fetch(PDO::FETCH_ASSOC);
            $pm_account = json_decode($raw_pm['details']);

            $payment_select = $client->prepare("SELECT * FROM ExpectedPayments WHERE id = ?");
            if($payment_select->execute([$paymentId]) && $payment_select->rowCount() > 0){
                $expected_payment = $payment_select->fetch(PDO::FETCH_OBJ);
                $original_passphrase_hash = strtoupper(md5($pm_account->alternatePassphrase));
                $computed_v2_hash = strtoupper(md5($_POST['PAYMENT_ID'].':'.$_POST['PAYEE_ACCOUNT'].':'.$_POST['PAYMENT_AMOUNT'].':'.$_POST['PAYMENT_UNITS'].':'.$_POST['PAYMENT_BATCH_NUM'].':'.$_POST['PAYER_ACCOUNT'].':'.$original_passphrase_hash.':'.$_POST['TIMESTAMPGMT']));

                if($v2_hash === $computed_v2_hash){
                    /// This payment is authentic.
                    $confirmation = new ConfirmationData(
                        generateHash(),
                        "perfectmoney",
                        protectString($_POST['PAYMENT_ID']),
                        doubleval($_POST['PAYMENT_AMOUNT']),
                        protectString($_POST['PAYMENT_UNITS']),
                        protectString($_POST['PAYER_ACCOUNT']),
                        protectString($_POST['PAYEE_ACCOUNT']),
                        protectString($_POST['PAYMENT_BATCH_NUM']),
                        intval($_POST['TIMESTAMPGMT'])
                    );

                    if(
                        $confirmation->amount >= doubleval($expected_payment->amount) &&
                        $confirmation->units === $expected_payment->currency
                    ){
                        return $confirmation;
                    }
                }
            }
        }
    }
    return null;
}

function prepareFedaPayTransaction(PDO $client, string $txId, string $paymentId){
    $payment_select = $client->prepare("SELECT * FROM ExpectedPayments WHERE id = ?");
    if($payment_select->execute([$paymentId]) && $payment_select->rowCount() > 0){
        $expected_payment = $payment_select->fetch(PDO::FETCH_OBJ);

        $methodAccountProvider = new MethodAccountProvider($client);
        $feda = $methodAccountProvider->getFedaPay();

        FedaPay::setEnvironment('live');
        FedaPay::setApiKey($feda['details']['privateKey']);
        $transaction = Transaction::retrieve($txId);
        if(
            $transaction->status === "approved" && 
            floatval($transaction->amount) === floatval($expected_payment->amount)
        ){
            $meta = $transaction->metadata;
            $payment_number = $meta->paid_phone_number;
            $number = $payment_number->number;

            $mode = "";
            if($transaction->mode === "mtn"){
                $mode = "mtnmobilemoney";
            }
            elseif($transaction->mode === "moov"){
                $mode = "moovmoney";
            }else{
                $mode = $transaction->mode;
            }

            return new ConfirmationData(
                generateHash(),
                $mode,
                $paymentId,
                floatval($transaction->amount),
                $expected_payment->currency,
                $number,
                $expected_payment->address,
                $txId,
                time()
            );
        }

    }
    return null;
}

class PaymentGateway {
    /**
     * The defined payment method. either BTC or PerfectMoney or Mobile Money or whatever else
     */
    public $method;

    /**
     * The payment source. for perfect money it is the account address 
     */
    public $from;

    /**
     * The  address to which the defined amount should be transferred to
     */
    public $to;

    /**
     * The amount to transfer
     */
    public $amount;

    /**
     * The currency defined for amount to transfer
     */
    public $currency;

    /**
     * a description provided (if applicable) to the payment
     */
    public $memo;

    /**
     * The  ticket for which the payment is been done
     */
    public $ticket;

    /**
     * The customer data associated to this payout
     */
    public $customer;

    /**
     * Country code of customer's given number (if method is mtn mobile money)
     */
    public $country;

    /**
     * The id of the payment data made by the customer, which launched out this transaction
     */
    public $expectedPayment;

    /**
     * The database instance passed. 
     */

    public $database;

    public function __construct(
        string $memo,
        stdClass $ticket,
        string $expectedPayment, PDO $database = null){
        
        $this->method = $ticket->dest->type;
        $this->from = PaymentGateway::addressFromMethod($ticket->dest);
        $this->to = $ticket->address;
        $this->amount = PaymentGateway::extractFees($ticket);
        $this->currency = PaymentGateway::getCurrencyFromMethod($ticket->dest);
        $this->memo = $memo;
        $this->ticket = $ticket;
        $this->expectedPayment = $expectedPayment;
        $this->database = $database;
    }
    
    public function setCountry(string $code){
        $this->country = $code;
    }

    public function setCustomer(stdClass $custom){
        $this->customer = $custom;
    }

    public function process(){
        global $logger;
        if($this->method === "internal"){
            return $this->processInternal();
        }
        else if($this->method === "perfectmoney"){
            return $this->processPerfectMoney();
        }
        else if($this->method === "BTC" || $this->method === "ETH"){
            $logger->info("Dispatching process to coinbase handler. Method: ".$this->method);
            return $this->processCryptocurrency();
        }
        else if($this->method === "mtnmobilemoney"){
            return $this->processFedaPayMobile();
        }
        else{
            return null;
        }
    }

    private function processInternal(){
        global $logger;
        $provider = new WalletProvider($this->database);
        $result = $provider->deposit($this->to,$this->amount, $this->currency, $this->memo);
        if(isset($result) && !empty($result)){
            // Transfer to internal account is done.
            // Let's return confirmation data
            $confirmation = new ConfirmationData(
                generateHash(),
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
        $methodAccountProvider = new MethodAccountProvider($this->database);
        $coinbase_account = $methodAccountProvider->getCoinbase();

        $coinbaseUtils = new CoinbaseUtils($coinbase_account, $logger);

        $compatible_accounts = $coinbaseUtils->getWalletsByCurrency(strtoupper($this->currency));

        if(!empty($compatible_accounts)){
            $transaction = new SendTransaction(
                $this->to,
                $this->amount,
                $this->currency,
                "Conversion 1xCrypto"
            );
            foreach($compatible_accounts as $k => $wallet){
                try{
                    $balance = doubleval($wallet->balance->amount);
                    if(PaymentGateway::canSendAmount(
                        $balance,
                        $this->amount,
                        PaymentGateway::calculateSendingFees($this->ticket)
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
                            generateHash(),
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
        $methodAccountProvider = new MethodAccountProvider($this->database);
        $pm_account = $methodAccountProvider->getPerfectMoney();
        $pmAccount = new PerfectMoney($pm_account['details']['accountId'], $pm_account['details']['passphrase']);
        $balance = $pmAccount->getBalance($this->ticket->dest->details->account);

        //Check if there is enough money to initiate payment
        if($balance > 0 && PaymentGateway::canSendAmount($balance, $this->amount, PaymentGateway::calculateSendingFees($this->ticket))){
            $spend = new PerfectMoneySpend(
                $this->ticket->dest->details->account,
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
            $methodAccountProvider = new MethodAccountProvider($this->database);
            $feda = $methodAccountProvider->getFedaPay();

            FedaPay::setEnvironment('live');
            FedaPay::setApiKey($feda['details']['privateKey']);

            // Les transactions automatiques ne sont actuellement supportées que pour MTN MOBILE MONEY
            if($this->method !== "mtnmobilemoney"){
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
                    generateHash(),
                    "mtnmobilemoney",
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

    public static function calculateSendingFees(stdClass $ticket){
        $global = doubleval($ticket->amount * $ticket->rate);
        $dest = $ticket->dest;

        $staticFee = doubleval($dest->details->staticFee);
        $dynamicFee = doubleval($dest->details->dynamicFee);

        $fees = $staticFee + ($global * $dynamicFee/100);
        return $fees;
    }

    public static function calculateEmitterBonus(stdClass $ticket){
        $global = doubleval($ticket->amount * $ticket->rate);
        $dest = $ticket->dest;

        $gain = doubleval($dest->percentage);
        $emitterBonus = doubleval($dest->details->emitterFee);

        return (($global * $gain/100) * $emitterBonus/100);
    }

    public static function calculateFees(stdClass $ticket){
        $global = doubleval($ticket->amount * $ticket->rate);
        $dest = $ticket->dest;

        $staticFee = doubleval($dest->details->staticFee);
        $dynamicFee = doubleval($dest->details->dynamicFee);
        $gain = doubleval($dest->percentage);

        $fees = $staticFee + ($global * $gain/100) + ($global * $dynamicFee/100);
    }

    /**
     * Used to extract fees from default amount
     */
    public static function extractFees(stdClass $ticket){
        $global = doubleval($ticket->amount * $ticket->rate);
        $dest = $ticket->dest;

        $staticFee = doubleval($dest->details->staticFee);
        $dynamicFee = doubleval($dest->details->dynamicFee);
        $gain = doubleval($dest->percentage);

        $fees = $staticFee + ($global * $gain/100) + ($global * $dynamicFee/100);

        return $global - $fees;
    }

    public static function getCurrencyFromMethod(stdClass $method){
        if($method->category === "cryptocurrency"){
            return $method->type;
        }
        return $method->details->currency;
    }

    public static function addressFromMethod(stdClass $method){

        if($method->category === "mobile"){
            return $method->details->address;
        }
        else if($method->category === "banking" || $method->category === "transfer"){
            return $method->details->account;
        }
        return "";
    }
}

?>