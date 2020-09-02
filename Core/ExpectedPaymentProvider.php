<?php
namespace Core;

use Coinbase\Wallet\Client;
use Coinbase\Wallet\Configuration;
use Coinbase\Wallet\Resource\Address;
use CoinbaseUtils;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Models\ExpectedPayment;
use Models\Ticket;
use Models\User;
use PDO;

class ExpectedPaymentProvider extends Provider{

    public function getExpectedPaymentByTicketId(string $id){
        $expectation_query = "`SELECT data FROM ExpectedPayments WHERE JSON_EXTRACT(data,'$.ticketId') = '$id'`";
        $expectation_stmt = $this->client->query($expectation_query);
        if($expectation_stmt->rowCount() > 0){
            $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
            return new ExpectedPayment(json_decode($expectation['data']));
        }
        return null;
    }

    public function getExpectedPaymentById(string $id){
        $expectation_query = "SELECT data FROM ExpectedPayments WHERE id = '$id'";
        $expectation_stmt = $this->client->query($expectation_query);
        if($expectation_stmt->rowCount() > 0){
            $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
            return new ExpectedPayment(json_decode($expectation['data']));
        }
        return null;
    }

    public function getExpectedPaymentByAddress(string $addr){
        $expectation_query = "`SELECT data FROM ExpectedPayments WHERE JSON_EXTRACT(data,'$.address') = '$addr'`";
        $expectation_stmt = $this->client->query($expectation_query);
        if($expectation_stmt->rowCount() > 0){
            $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
            return new ExpectedPayment(json_decode($expectation['data']));
        }
        return null;
    }

    public function createExpectedPayment(User $user, Ticket $ticket){
        global $logger;
        $paymentId = generateHash();
        $expectedAddress = "";
        $paymentUrl = "" ; /// Useful for fedapay links;
        $source = $ticket->source;
        $source_currency = PaymentGateway::getCurrencyFromMethod(json_decode(json_encode($source)));

        $methodAccountProvider = new MethodAccountProvider($this->client);
        if($source['category'] === "cryptocurrency"){

            /**
             * Step 1 : Retrieve accounts;
             * Step 2 : retrieve the account which could support this ticket
             * Step 3 : Generate Unique Receive Link and bind it to the ticket
             * Step 4 : Subscribe to event on the address
             */
            $coinbase_account = $methodAccountProvider->getCoinbase();

            if(!isset($coinbase_account) || !isset($coinbase_account['details'])){
                return "";
            }

            $coinbaseUtils = new CoinbaseUtils($coinbase_account, $logger);
            $wallets = $coinbaseUtils->getWalletsByCurrency($source_currency);

            $logger->info("Crypto: $source_currency ; Compatible wallets: ".json_encode($wallets));

            if(isset($wallets[0])){
                $wallet = $wallets[0];

                $generated_address = $coinbaseUtils->createAddress(
                    'Paiement 1xCrypto par '.$user['firstName'].' '.$user['lastName'],
                    $wallet->id
                );

                if(isset($generated_address)){
                    $expectedAddress = $generated_address->address;
                }
            }

            if(!isset($expectedAddress) || empty($expectedAddress)){
                /// NO Coinbase crypto account found available to handle this ticket;
                /// Therefore we could not generate a payment address 
                /// We can't handle this ticket.
                return "";
            }
        }
        else if($source['category'] === "mobile"){
            $feda_account = $methodAccountProvider->getFedaPay();
            FedaPay::setEnvironment("live");
            FedaPay::setApiKey($feda_account['details']['privateKey']);

            $expectedAddress = $source['details']['address'];

            $fedaTrans = Transaction::create([
                'description' => "Paiement de {$ticket['amount']} $source_currency a 1xCrypto",
                'amount' => $ticket['amount'],
                'callback_url' => "https://api.1xcrypto.net/payments/confirm/mobile/$paymentId",
                'currency' => [
                    'iso' => $source_currency
                ],
                'customer' => [
                    'firstname' => $user['firstName'],
                    'lastname' => $user['lastName'],
                    'email' => $user['email']
                ]
            ]);

            $paymentUrl = $fedaTrans->generateToken()->url;
        }
        else{
            $expectedAddress = $source['details']['account'];
            $paymentUrl = "";
        }

        $expected_payment_query = "INSERT INTO ExpectedPayments(id, data) VALUES(?,?)";
        $expectation_stmt = $this->client->prepare($expected_payment_query);

        $data = [
            'id' => $paymentId,
            'ticketId' => $ticket->id,
            'type' => $ticket->source->type,
            'amount' => $ticket->amount,
            'currency' => $source_currency,
            'address' => $expectedAddress,
            'paymentUrl' => $paymentUrl
        ];

        if($expectation_stmt->execute([
            $paymentId,
            json_encode($data)
        ])){
            return $paymentId;
        }

        return "";
    }

    public function deleteExpectedPayment(string $id){
        $payment_deletion_query = "DELETE FROM ExpectedPayments WHERE id = ?";
        $payment_deletion_stmt = $this->client->prepare($payment_deletion_query);

        if($payment_deletion_stmt->execute([$id])){
            return true;
        }
        return false;
    }
}
?>