<?php
namespace Core{

    use Coinbase\Wallet\Client;
    use Coinbase\Wallet\Configuration;
    use Coinbase\Wallet\Resource\Address;
    use CoinbaseUtils;
    use FedaPay\FedaPay;
    use FedaPay\Transaction;
    use PaymentGateway;
    use PDO;

class ExpectedPaymentProvider{
        public $client;

        public function __construct(PDO $client){
            $this->client = $client;
        }


        public function getExpectedPaymentByTicketId(string $id){
            $expectation_query = "SELECT ExpectedPayments.id , ticketId, type, ExpectedPayments.amount, currency, ExpectedPayments.address, paymentUrl FROM ExpectedPayments INNER JOIN Tickets on ExpectedPayments.ticketId = Tickets.id WHERE ticketId = '$id' and Tickets.allowed = 1";
            $expectation_stmt = $this->client->query($expectation_query);
            if($expectation_stmt->rowCount() > 0){
                $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
                return $expectation;
            }
            return null;
        }

        public function getExpectedPaymentById(string $id){
            $expectation_query = "SELECT ExpectedPayments.id , ticketId, type, ExpectedPayments.amount, currency, ExpectedPayments.address, paymentUrl FROM ExpectedPayments INNER JOIN Tickets on ExpectedPayments.ticketId = Tickets.id WHERE ExpectedPayments.id = '$id' and Tickets.allowed = 1";
            $expectation_stmt = $this->client->query($expectation_query);
            if($expectation_stmt->rowCount() > 0){
                $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
                return $expectation;
            }
            return null;
        }

        public function getExpectedPaymentByAddress(string $addr){
            $expectation_query = "SELECT ExpectedPayments.id , ticketId, type, ExpectedPayments.amount, currency, ExpectedPayments.address, paymentUrl FROM ExpectedPayments INNER JOIN Tickets on ExpectedPayments.ticketId = Tickets.id WHERE ExpectedPayments.address = '$addr' and Tickets.allowed = 1";
            $expectation_stmt = $this->client->query($expectation_query);
            if($expectation_stmt->rowCount() > 0){
                $expectation = $expectation_stmt->fetch(PDO::FETCH_ASSOC);
                return $expectation;
            }
            return null;
        }

        public function createExpectedPayment(array $user, array $ticket){
            global $logger;
            $paymentId = generateHash();
            $expectedAddress = "";
            $paymentUrl = "" ; /// Useful for fedapay links;
            $source = $ticket['source'];
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
                    'callback_url' => "https://1xcrypto.net/api/payments/confirm/mobile/$paymentId",
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

            $expected_payment_query = "INSERT INTO ExpectedPayments(id, ticketId,type, amount, currency, address,paymentUrl) VALUES(?,?,?,?,?,?,?)";
            $expectation_stmt = $this->client->prepare($expected_payment_query);
            
            if($expectation_stmt->execute([
                $paymentId,
                $ticket['id'],
                $source['type'],
                doubleval($ticket['amount']),
                $source_currency,
                $expectedAddress,
                $paymentUrl
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
}

?>