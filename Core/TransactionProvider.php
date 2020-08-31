<?php

namespace Core;

use PDO;
class TransactionProvider{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createInTicketTransaction(array $ticket, ConfirmationData $payment){
        return $this->createTicketTransaction($ticket, "in", $payment);
    }

    public function createOutTicketTransaction(array $ticket, ConfirmationData $payment){
        return $this->createTicketTransaction($ticket, "out", $payment);
    }

    public function createTicketTransaction(array $ticket, string $variant = 'in', ConfirmationData $payment){
        $out_transaction_query = "INSERT INTO Transactions(id, ticketId, variant, type, reference, amount, currency, source, dest, insertionDate, status) VALUES (?,?,?,?,?,?,?,?,?,NOW(), ?)";
        $out_transaction_stmt = $this->client->prepare($out_transaction_query);

        $previous = $this->getTransactionByReference($payment->transactionId);

        if(isset($previous) && isset($previous['id']) && isset($previous['type']) && $previous['type'] === $payment->type){
            return "";
        }
        
        $transactionId = generateHash();
        if($out_transaction_stmt->execute([
            $transactionId,
            $ticket['id'],
            $variant,
            $payment->type,
            $payment->transactionId,
            $payment->amount,
            $payment->units,
            $payment->source,
            $payment->destination,
            "pending"
            ])){
            
            if($payment->isDone){
                $this->client->query("UPDATE Transactions set status = 'done', validationDate = NOW() WHERE id = '$transactionId'");
                if($variant === "in"){
                    $this->client->query("UPDATE Tickets SET status = 'confirmed', confirmedAt = NOW() WHERE id = '{$ticket['id']}'");
                }
                else if($variant === "out"){
                    $this->client->query("UPDATE Tickets SET status = 'paid', paidAt = NOW() WHERE id = '{$ticket['id']}'");
                }
            }
            return $transactionId;
        }
    }

    public function getTransactions(){
        $fetch_tsx_query = "SELECT * FROM Transactions ORDER BY insertionDate DESC";
        $stmt = $this->client->query($fetch_tsx_query);
    
        if($stmt){
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $rows;
        }
    
        return null;
    }

    public function getTransactionById(string $id){
        $fetch_tsx_query = "SELECT * FROM Transactions WHERE id = ?";
        $stmt = $this->client->prepare($fetch_tsx_query);
    
        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row;
        }
    
        return null;
    }

    public function getTransactionByReference(string $ref){
        $fetch_tsx_query = "SELECT * FROM Transactions WHERE reference = ?";
        $stmt = $this->client->prepare($fetch_tsx_query);
    
        if($stmt->execute([$ref]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row;
        }
    
        return null;
    }
}
?>