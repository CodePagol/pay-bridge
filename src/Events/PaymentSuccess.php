<?php

namespace PayBridge\Payment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccess
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transactionId;
    public $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $transactionId, array $payload = [])
    {
        $this->transactionId = $transactionId;
        $this->payload = $payload;
    }
}
