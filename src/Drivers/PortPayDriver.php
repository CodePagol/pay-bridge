<?php

namespace PayBridge\Payment\Drivers;

class PortPayDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        // Example base URLs based on generic PortPay/Payment Gateway standards
        return $this->config['sandbox']
            ? 'https://sandbox.portpay.io/api/v1'
            : 'https://api.portpay.io/api/v1';
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('port_');

            // Standard REST Payload for a gateway like PortPay
            $payload = [
                'app_key' => $this->config['app_key'],
                'secret_key' => $this->config['secret_key'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'order_id' => $orderId,
                'customer_name' => $data['customer_name'] ?? 'Customer',
                'customer_email' => $data['customer_email'] ?? 'customer@example.com',
                'customer_phone' => $data['customer_phone'] ?? '01700000000',
                'redirect_url' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/portpay/callback'),
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/portpay/cancel'),
            ];

            $response = $this->client->post($this->getBaseUrl() . '/checkout/create', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['payment_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $responseData['payment_url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to get PortPay checkout URL', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('PortPay Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            $orderId = $data['order_id'] ?? ($data['transaction_id'] ?? null);

            if (!$orderId) {
                return $this->formatResponse(false, 'Missing transaction ID for PortPay verification');
            }

            $response = $this->client->post($this->getBaseUrl() . '/checkout/verify', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'app_key' => $this->config['app_key'],
                    'secret_key' => $this->config['secret_key'],
                    'order_id' => $orderId
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['status']) && strtolower($responseData['status']) === 'paid') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('PortPay Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'PortPay refund logic pending specific API endpoint definitions.', $transactionId);
    }

    public function webhook(array $payload): array
    {
        $orderId = $payload['order_id'] ?? null;

        // PortPay usually posts JSON webhook
        if (isset($payload['status']) && strtolower($payload['status']) === 'paid') {
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', $orderId, null, $payload);
    }
}
