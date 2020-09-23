<?php

namespace Core;

use Models\Ticket;
use PDO;
use Utils\Utils;

class TicketProvider extends Provider{

    public function getCreationScript(): array{
        return [
            'CREATE TABLE IF NOT EXISTS Tickets(
                id VARCHAR(255) NOT NULL,
                data JSON NOT NULL,
                primary key(id)
            );'
        ];
    }

    public function createTicket(array $ticket){
        $insert_query = "INSERT INTO Tickets(id,data) values(?,?)";
        $stmt = $this->client->prepare($insert_query);
        $ticket['id'] = Utils::generateHash();
        $ticket['rate'] = doubleval($ticket['rate']);
        $ticket['amount'] = doubleval($ticket['amount']);
        $ticket['emittedAt'] = time()*1000; // moving from seconds to milliseconds;

        if($stmt->execute([
            $ticket['id'],
            \json_encode($ticket)
        ])){
            return $ticket['id'];
        }
        return '';
    }

    public function getTickets(){
        $query = "SELECT * FROM Tickets ORDER BY JSON_EXTRACT(data,'$.emittedAt') DESC";
        $stmt = $this->client->query($query);
        $rows = [];
        if($stmt && $stmt->rowCount() > 0){
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($rows, new Ticket(json_decode($row['data'])));
            }
        }
        return $rows;
    }

    public function getTicketsByUser(string $userId){
        $query = "SELECT * FROM Tickets WHERE JSON_EXTRACT(data,'$.userId') = ? ORDER BY JSON_EXTRACT(data,'$.emissionDate') DESC";
        $stmt = $this->client->prepare($query);
        $rows = [];
        if($stmt->execute([$userId])){
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC))){
                array_push($rows, new Ticket(json_decode($row['data'])));
            }
        }
    
        return $rows;
    }
    
    public function getTicketById(string $ticketId){
        $query = "SELECT * FROM Tickets WHERE id = ? ORDER BY JSON_EXTRACT(data,'$.emissionDate') DESC";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$ticketId]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return new Ticket(json_decode($row['data']));
        }
    
        return null;
    }

    public function approveTicket(string $ticketId){
        $query = "UPDATE Tickets SET data = JSON_SET(data,'$.allowed', true) WHERE id = '$ticketId' AND JSON_EXTRACT(data,'$.status') = 'pending'";
        if($this->client->query($query)){
            return true;
        }
        return false;
    }

    public function abortTicket(string $ticketId){
        //Step 1: Fetch Payment Data
        //Step 2: DELETE Code
        //Step 3: DELETE PaymentData
        //Step 4: Mark Ticket as cancelled
        $this->client->beginTransaction();
        $expectedPaymentProvider = new ExpectedPaymentProvider($this->client);
        $expected_payment = $expectedPaymentProvider->getExpectedPaymentByTicketId($ticketId);
 
        if(isset($expected_payment)){
            $expectedPaymentProvider->deleteExpectedPayment($expected_payment->id);
        }
        $ticket = $this->getTicketById($ticketId);
        if($ticket !== null){
            $ticket->status = Ticket::STATUS_CANCELLED;
            $ticket->cancelledAt = time();
            $abort_query = "UPDATE Tickets SET data = ? WHERE id = '$ticketId'";
            $stmt = $this->client->prepare($abort_query);
            if($this->client->query($abort_query)){
                $this->client->commit();
                return true;
            }
            $this->client->rollBack();
        }
        return false;
    } 
}

?>