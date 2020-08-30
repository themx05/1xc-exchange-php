<?php

namespace Core{

use PDO;

class TicketProvider{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createTicket(array $ticket){
        $insert_query = "INSERT INTO Tickets(id,data) values(?,?)";
        $stmt = $this->client->prepare($insert_query);

        $id = generateHash();
        $ticket['id'] = $id;
        $ticket['rate'] = doubleval($ticket['rate']);
        $ticket['amount'] = doubleval($ticket['amount']);
        $ticket['emittedAt'] = time();

        if($stmt->execute([
            $id,
            \json_encode($ticket)
        ])){
            return $id;
        }
        return '';
    }

    public function getTickets(){
        $query = "SELECT * FROM Tickets ORDER BY JSON_EXTRACT(data,'$.emittedAt') DESC";
        $stmt = $this->client->query($query);
        if($stmt){
            $rows = [];
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) != false){
                $row['data'] = json_decode($row['data'],true);
                array_push($rows, $row['data']);
            }
            return $rows;
        }
        return null;
    }

    public function getTicketsByUser(string $userId){
        $query = "SELECT * FROM Tickets WHERE userId = ? ORDER BY JSON_EXTRACT(data,'$emissionDate') DESC";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$userId])){
            $rows = [];
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) != false){
                $row['data'] = json_decode($row['data'],true);
                array_push($rows, $row['data']);
            }
            return $rows;
        }
    
        return null;
    }
    
    public function getTicketById(string $ticketId){
        $query = "SELECT * FROM Tickets WHERE id = ? ORDER BY JSON_EXTRACT(data,'$emissionDate') DESC";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$ticketId]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return json_decode($row['data'],true);
        }
    
        return null;
    }

    public function approveTicket(string $ticketId){
        $query = "UPDATE Tickets SET data = JSON_SET(data,'$allowed', true) WHERE id = '$ticketId' AND JSON_EXTRACT(data,'$status') = 'pending'";
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
            $expectedPaymentProvider->deleteExpectedPayment($expected_payment['id']);
        }
        $abort_query = "UPDATE Tickets SET data = JSON_SET(data,'$status', 'cancelled'), cancelledAt=NOW() WHERE id = '$ticketId'";
        if($this->client->query($abort_query)){
            $this->client->commit();
            return true;
        }
        $this->client->rollBack();
        return false;
    } 
}

}

?>