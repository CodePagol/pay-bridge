<?php

namespace PayBridge\Payment\Drivers;

class EpsDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        // Example base URLs based on generic EPS Gateway standards
        return $this->config['sandbox']
            ? 'https://sandbox.eps.com.bd/api/v1'
            : 'https://api.eps.com.bd/api/v1';
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('eps_');

            // Standard REST Payload for a gateway like EPS
            $payload = [
                'merchant_id' => $this->config['merchant_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'tran_id' => $orderId,
                'cus_name' => $data['customer_name'] ?? 'Customer',
                'cus_email' => $data['customer_email'] ?? 'customer@example.com',
                'cus_phone' => $data['customer_phone'] ?? '01700000000',
                'success_url' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/eps/callback'),
                'fail_url' => $this->resolveUrl($this->config['fail_url'] ?? '/payment/eps/fail'),
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/eps/cancel'),
            ];

            $response = $this->client->post($this->getBaseUrl() . '/checkout/initiate', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // EPS format check (assumed modern structured output)
            if (isset($responseData['status']) && $responseData['status'] === 'success' && isset($responseData['payment_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $responseData['payment_url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to get EPS checkout URL', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('EPS Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            $orderId = $data['tran_id'] ?? ($data['transaction_id'] ?? null);

            if (!$orderId) {
                return $this->formatResponse(false, 'Missing transaction ID for EPS verification');
            }

            $response = $this->client->post($this->getBaseUrl() . '/checkout/verify', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'merchant_id' => $this->config['merchant_id'],
                    'tran_id' => $orderId
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Typical verification status parsing
            if (isset($responseData['pay_status']) && strtolower($responseData['pay_status']) === 'successful') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('EPS Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'EPS refund logic pending specific API endpoint access.', $transactionId);
    }

    public function webhook(array $payload): array
    {
        $orderId = $payload['tran_id'] ?? null;

        if (isset($payload['pay_status']) && strtolower($payload['pay_status']) === 'successful') {
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', $orderId, null, $payload);
    }
}
