<?php

namespace PayBridge\Payment\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transactionId;
    public $error;
    public $payload;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $transactionId, string $error, array $payload = [])
    {
        $this->transactionId = $transactionId;
        $this->error = $error;
        $this->payload = $payload;
    }
}
