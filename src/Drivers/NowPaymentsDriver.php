<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class NowPaymentsDriver extends AbstractGatewayDriver
{
    private string $sandboxUrl = 'https://api-sandbox.nowpayments.io/v1';
    private string $liveUrl    = 'https://api.nowpayments.io/v1';

    protected function getBaseUrl(): string
    {
        return $this->isSandbox() ? $this->sandboxUrl : $this->liveUrl;
    }

    protected function getHeaders(): array
    {
        return [
            'x-api-key'    => $this->config['api_key'] ?? '',
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Initiate a cryptocurrency payment by creating a hosted NOWPayments Invoice.
     */
    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? $this->generateUuid();

            $payload = [
                'price_amount'      => (float)$data['amount'],
                'price_currency'    => strtolower($data['currency'] ?? 'usd'),
                'order_id'          => $orderId,
                'order_description' => $data['product_name'] ?? ('Payment for order ' . $orderId),
                'ipn_callback_url'  => $this->resolveUrl($data['ipn_url'] ?? ($this->config['ipn_url'] ?? '/payment/nowpayments/ipn')),
                'success_url'       => $this->resolveUrl($data['success_url'] ?? ($this->config['success_url'] ?? '/payment/nowpayments/success')),
                'cancel_url'        => $this->resolveUrl($data['cancel_url'] ?? ($this->config['cancel_url'] ?? '/payment/nowpayments/cancel')),
            ];

            if (!empty($data['pay_currency'])) {
                $payload['pay_currency'] = strtolower($data['pay_currency']);
            }

            $response = $this->client->post($this->getBaseUrl() . '/invoice', [
                'headers' => $this->getHeaders(),
                'json'    => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            if (isset($responseData['invoice_url'])) {
                return $this->formatResponse(
                    true,
                    'NOWPayments invoice created successfully',
                    $orderId,
                    $responseData['invoice_url'],
                    $responseData
                );
            }

            $message = $responseData['message'] ?? 'Failed to create NOWPayments invoice';
            return $this->formatResponse(false, $message, $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('NOWPayments pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Verify payment status using payment_id.
     */
    public function verify(array $data): array
    {
        try {
            $paymentId = $data['payment_id'] ?? null;

            if (!$paymentId) {
                return $this->formatResponse(false, 'Missing payment_id for NOWPayments verification');
            }

            $response = $this->client->get($this->getBaseUrl() . '/payment/' . $paymentId, [
                'headers' => $this->getHeaders(),
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            $status = $responseData['payment_status'] ?? null;
            $orderId = $responseData['order_id'] ?? null;

            if (in_array($status, ['finished', 'confirmed'])) {
                $amount = isset($responseData['price_amount']) ? (float)$responseData['price_amount'] : (isset($responseData['pay_amount']) ? (float)$responseData['pay_amount'] : null);
                $currency = $responseData['price_currency'] ?? ($responseData['pay_currency'] ?? 'usd');

                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData, $amount, $currency);
            }

            if (in_array($status, ['waiting', 'confirming', 'sending'])) {
                return $this->formatResponse(false, "Payment is in pending status: {$status}", $orderId, null, $responseData);
            }

            return $this->formatResponse(false, "Payment failed with status: {$status}", $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('NOWPayments verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Refund a processed payment.
     */
    public function refund(string $transactionId): array
    {
        // NOWPayments doesn't have an automated API refund endpoint for standard merchants.
        // Payouts API must be used for custodial wallets.
        return $this->formatResponse(
            false,
            'NOWPayments automated refunds require Custodial Sub-partner API. Please process refund manually through the NOWPayments merchant dashboard.',
            $transactionId
        );
    }

    /**
     * Handle incoming IPN / Webhook from NOWPayments.
     */
    public function webhook(array $payload): array
    {
        try {
            $ipnSecret = $this->config['ipn_secret'] ?? null;

            if (empty($ipnSecret)) {
                $this->logCritical('NOWPayments Webhook Error: IPN Secret is not configured. Webhook rejected for security.');
                return $this->formatResponse(false, 'NOWPayments IPN secret is not configured on server.', null, null, $payload);
            }

            // Verify signature
            $receivedSig = $this->getHeader('x-nowpayments-sig');
            if (empty($receivedSig)) {
                return $this->formatResponse(false, 'Missing NOWPayments IPN signature header (x-nowpayments-sig)', null, null, $payload);
            }

            $sortedPayload = $payload;
            ksort($sortedPayload);
            $sortedJson = json_encode($sortedPayload, JSON_UNESCAPED_SLASHES);
            $calculatedSig = hash_hmac('sha512', $sortedJson, $ipnSecret);

            if (!hash_equals($calculatedSig, $receivedSig)) {
                $this->logWarning('NOWPayments Webhook Error: Signature mismatch.');
                return $this->formatResponse(false, 'Invalid NOWPayments IPN signature', null, null, $payload);
            }

            $paymentStatus = $payload['payment_status'] ?? null;
            $orderId = $payload['order_id'] ?? ($payload['payment_id'] ?? null);
            $amount = isset($payload['price_amount']) ? (float)$payload['price_amount'] : (isset($payload['pay_amount']) ? (float)$payload['pay_amount'] : null);
            $currency = $payload['price_currency'] ?? ($payload['pay_currency'] ?? 'usd');

            if (in_array($paymentStatus, ['finished', 'confirmed'])) {
                return $this->formatResponse(true, 'NOWPayments Webhook: Payment confirmed', $orderId, null, $payload, $amount, $currency);
            }

            return $this->formatResponse(false, "NOWPayments Webhook status: {$paymentStatus}", $orderId, null, $payload);

        } catch (\Exception $e) {
            $this->logError('NOWPayments webhook() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }
}
