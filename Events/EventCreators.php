<?php
namespace Events;

use Models\Money;
use Models\Ticket;
use Models\Transaction;
use Models\Wallet;

class EventCreators{

    static function eventTicketSubmitted(Ticket $ticket){
        return [
            'type' => TicketEvent::EVENT_TICKET_SUBMITTED,
            'payload' => [
                'document' => [
                    'id' =>  $ticket->id,
                    'data' => $ticket
                ]
            ],
            'timestamp' => time()*1000
        ];
    }

    static function eventTicketPaid(Ticket $ticket, Transaction $transaction){
        return [
            'type' => TicketEvent::EVENT_TICKET_PAID,
            'payload' => [
                'ticket' => $ticket,
                'transaction' => $transaction
            ],
            'timestamp' => time()*1000
        ];
    }

    static function eventTicketConfirmed(Ticket $ticket, Transaction $transaction){
        return [
            'type' => TicketEvent::EVENT_TICKET_CONFIRMED,
            'payload' => [
                'ticket' => [
                    'id' =>  $ticket->id,
                    'data' => $ticket
                ],
                'transaction' => [
                    'id' => $transaction->id,
                    'data' => $transaction
                ]
            ],
            'timestamp' => time()*1000
        ];
    }

    static function eventTicketCommissionPaid(Ticket $ticket, Wallet $wallet, Money $amount, string $history){
        return [
            'type' => TicketEvent::EVENT_TICKET_COMMISSION_PAID,
            'payload' => [
                'ticket' => [
                    'id' =>  $ticket->id,
                    'data' => $ticket
                ],
                'wallet' => $wallet->id,
                'amount' => $amount,
                'history' => $history
            ],
            'timestamp' => time()*1000
        ];
    }

    static function eventTicketPaymentFailed(Ticket $ticket, Money $amount){
        return [
            'type' => TicketEvent::EVENT_TICKET_PAID,
            'payload' => [
                'ticket' => [
                    'id' =>  $ticket->id,
                    'data' => $ticket
                ],
                'amount' => $amount
            ],
            'timestamp' => time()*1000
        ];
    }
}

?>