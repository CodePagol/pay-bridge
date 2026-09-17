<?php

namespace PayBridge\Payment\Drivers;

class UpayDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->config['sandbox']
            ? 'https://uat-pg.upaybd.com'
            : 'https://pg.upaybd.com';
    }

    /**
     * Authenticate to get UPAY token
     */
    protected function authenticate(): string
    {
        $response = $this->client->post($this->getBaseUrl() . '/payment/merchant-auth/', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'merchant_id' => $this->config['merchant_id'],
                'merchant_key' => $this->config['merchant_key']
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['data']['token'])) {
            return $data['data']['token'];
        }

        throw new \PayBridge\Payment\Exceptions\PaymentException('UPAY Authentication Failed: ' . json_encode($data));
    }

    public function pay(array $data): array
    {
        try {
            $token = $this->authenticate();
            $orderId = $data['transaction_id'] ?? uniqid('upay_');

            $payload = [
                'date' => date('Y-m-d'),
                'txn_id' => $orderId,
                'invoice_id' => $orderId,
                'amount' => $data['amount'],
                'merchant_id' => $this->config['merchant_id'],
                'merchant_name' => $this->config['merchant_name'] ?? 'PayBridge Merchant',
                'merchant_code' => $this->config['merchant_code'] ?? '1234',
                'merchant_country_code' => 'BD',
                'merchant_city' => 'Dhaka',
                'merchant_category_code' => '0000',
                'merchant_mobile' => '01700000000',
                'transaction_currency_code' => $data['currency'] ?? 'BDT',
                'redirect_url' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/upay/callback'),
            ];

            $response = $this->client->post($this->getBaseUrl() . '/payment/merchant-payment-init/', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['data']['gateway_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $responseData['data']['gateway_url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to get Upay checkout URL', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Upay Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            $orderId = $data['txn_id'] ?? ($data['invoice_id'] ?? null);

            if (!$orderId) {
                return $this->formatResponse(false, 'Missing transaction ID for Upay verification');
            }

            $token = $this->authenticate();

            $response = $this->client->get($this->getBaseUrl() . '/payment/single-payment-status/' . $orderId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'successful') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Upay Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        // Upay refund requires a separate signed API or manual process
        return $this->formatResponse(false, 'Upay refund API requires explicit configuration and is often handled manually by merchants.', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // Upay typically redirects or posts 'status' back
        $orderId = $payload['txn_id'] ?? ($payload['invoice_id'] ?? null);

        if (isset($payload['status']) && strtolower($payload['status']) === 'successful') {
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', $orderId, null, $payload);
    }
}
