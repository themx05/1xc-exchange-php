<?php

namespace Core;

use Models\Ticket;
use Models\Transaction;
use PDO;
use Utils\Utils;

class TransactionProvider extends Provider{

    public function getCreationScript(): array{
        return [
            'CREATE TABLE IF NOT EXISTS Transactions(
                id varchar(255) not null,
                data JSON NOT NULL,
                primary key(id)
            );'
        ];
    }    

    public function createInTicketTransaction(Ticket $ticket, ConfirmationData $payment){
        return $this->createTicketTransaction($ticket, Transaction::VARIANT_IN, $payment);
    }

    public function createOutTicketTransaction(Ticket $ticket, ConfirmationData $payment){
        return $this->createTicketTransaction($ticket, Transaction::VARIANT_OUT, $payment);
    }

    public function createTicketTransaction(Ticket $ticket, string $variant = Transaction::VARIANT_IN, ConfirmationData $payment){
        $out_transaction_query = "INSERT INTO Transactions(id, data) VALUES (?,?)";
        $out_transaction_stmt = $this->client->prepare($out_transaction_query);

        $previous = $this->getTransactionByReference($payment->transactionId);

        if(isset($previous) && isset($previous->id) && isset($previous->type) && $previous->type === $payment->type){
            return "";
        }
        $trans = [
            'id' => Utils::generateHash(),
            'ticketId' => $ticket->id,
            'variant' => $variant,
            'type' => $payment->type,
            'reference' => $payment->transactionId,
            'amount' => $payment->amount,
            'currency' => $payment->units,
            'source' => $payment->source,
            'dest' => $payment->dest,
            'insertionDate' => time()*1000, /// moved from seconds to milliseconds,
            'status' => Transaction::STATUS_PENDING
        ];

        if($out_transaction_stmt->execute([$trans['id'],$trans])){
            if($payment->isDone){
                $tx_v = $this->client->prepare("UPDATE Transactions SET data = ? WHERE id = ?");
                $trans['status'] = Transaction::STATUS_DONE;
                $trans['validationDate'] = time();
                if($tx_v->execute([\json_encode($trans), $trans['id']])){
                    if($variant === Transaction::VARIANT_IN){
                        $ticket->status = Ticket::STATUS_CONFIRMED;
                        $ticket->confirmedAt = time();
                    }
                    else if($variant === Transaction::VARIANT_OUT){
                        $ticket->status = Ticket::STATUS_PAID;
                        $ticket->paidAt = time();
                    }
                    $update_tickets = $this->client->prepare("UPDATE Tickets SET data = ?  WHERE id = ?");
                    $update_tickets->execute([\json_encode($ticket), $ticket->id]);
                }
            }
            return $trans['id'];
        }
        return "";
    }

    public function getTransactions(){
        $fetch_tsx_query = "SELECT * FROM Transactions";
        $stmt = $this->client->query($fetch_tsx_query);
        $rows  = [];
        if($stmt){
            $row = null;
            while( ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ){
                array_push($rows, new Transaction(json_decode($row['data'])));
            }
        }
        return $rows;
    }

    public function getTransactionById(string $id){
        $fetch_tsx_query = "SELECT * FROM Transactions WHERE id = ?";
        $stmt = $this->client->prepare($fetch_tsx_query);
        if($stmt->execute([$id]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Transaction(json_decode($row['data']));
        }
        return null;
    }

    public function getTransactionByReference(string $ref){
        $fetch_tsx_query = "SELECT * FROM Transactions WHERE JSON_EXTRACT(data,'$.reference') = ?";
        $stmt = $this->client->prepare($fetch_tsx_query);
        if($stmt->execute([$ref]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Transaction(json_decode($row['data']));
        }
        return null;
    }
}
?>