<?php

namespace PayBridge\Payment\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment transaction.
     *
     * @param array $data Standardized payload for payment.
     * @return array Standardized response containing success, message, transaction_id, etc.
     */
    public function pay(array $data): array;

    /**
     * Verify an existing payment transaction.
     *
     * @param array $data Can contain transaction_id, session_key, etc.
     * @return array Standardized response containing verification status.
     */
    public function verify(array $data): array;

    /**
     * Refund a processed payment transaction.
     *
     * @param string $transactionId The unique ID of the transaction to refund.
     * @return array Standardized response with refund status.
     */
    public function refund(string $transactionId): array;

    /**
     * Process incoming webhook / IPN requests from the gateway.
     *
     * @param array $payload Webhook payload data.
     * @return array Processed webhook data and verification status.
     */
    public function webhook(array $payload): array;
}
