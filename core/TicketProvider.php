<?php

namespace Core{

use PDO;

class TicketProvider{
    public $client;

    public function __construct(PDO $client){
        $this->client = $client;
    }

    public function createTicket(array $ticket){
        $insert_query = "INSERT INTO Tickets(id, userId,source,dest,rate,amount,address,allowed,enableCommission,status) values(?,?,?,?,?,?,?,?,?,?)";
        $stmt = $this->client->prepare($insert_query);

        $id = generateHash();
        if($stmt->execute([
            $id,
            $ticket['userId'],
            json_encode($ticket['source']),
            json_encode($ticket['dest']),
            doubleval($ticket['rate']),
            doubleval($ticket['amount']),
            $ticket['address'],
            $ticket['allowed'],
            $ticket['enableCommission'],
            $ticket['status']
        ])){
            return $id;
        }
        return '';
    }

    public function getTickets(){
        $query = "SELECT * FROM Tickets ORDER BY emissionDate DESC";
        $stmt = $this->client->query($query);
        if($stmt){
            $rows = [];
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) != false){
                $row['source'] = json_decode($row['source'],true);
                $row['dest'] = json_decode($row['dest'],true);
                array_push($rows, $row);
            }
            return $rows;
        }
        return null;
    }

    public function getTicketsByUser(string $userId){
        $query = "SELECT * FROM Tickets WHERE userId = ? ORDER BY emissionDate DESC";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$userId])){
            $rows = [];
            $row = null;
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC)) != false){
                $row['source'] = json_decode($row['source'],true);
                $row['dest'] = json_decode($row['dest'],true);
                array_push($rows, $row);
            }
            return $rows;
        }
    
        return null;
    }
    

    public function getTicketById(string $ticketId){
        $query = "SELECT * FROM Tickets WHERE id = ? ORDER BY emissionDate DESC";
        $stmt = $this->client->prepare($query);
        if($stmt->execute([$ticketId]) && $stmt->rowCount() > 0){
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $row['source'] = json_decode($row['source'],true);
            $row['dest'] = json_decode($row['dest'],true);
            return $row;
        }
    
        return null;
    }

    public function approveTicket(string $ticketId){
        $query = "UPDATE Tickets SET allowed = 1 WHERE id = '$ticketId' AND status = 'pending'";
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
        $abort_query = "UPDATE Tickets SET status = 'cancelled', cancelledAt=NOW() WHERE id = '$ticketId'";
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