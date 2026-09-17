<?php

namespace PayBridge\Payment\Drivers;

class SurjoPayDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->config['sandbox'] 
            ? 'https://sandbox.shurjopayment.com' 
            : 'https://engine.shurjopayment.com';
    }

    /**
     * Authenticate with SurjoPay to get a token
     */
    protected function authenticate(): array
    {
        $response = $this->client->post($this->getBaseUrl() . '/api/get_token', [
            'json' => [
                'username' => $this->config['merchant_name'],
                'password' => $this->config['merchant_password'],
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['token']) && isset($data['store_id'])) {
            return [
                'success' => true,
                'token' => $data['token'],
                'store_id' => $data['store_id'],
                'raw' => $data
            ];
        }

        throw new \PayBridge\Payment\Exceptions\PaymentException("SurjoPay Authentication Failed: " . json_encode($data));
    }

    public function pay(array $data): array
    {
        try {
            $auth = $this->authenticate();
            
            $payload = [
                'prefix' => $this->config['merchant_prefix'],
                'token' => $auth['token'],
                'return_url' => $this->resolveUrl($this->config['success_url'] ?? '/payment/surjopay/success'),
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/surjopay/cancel'),
                'store_id' => $auth['store_id'],
                'amount' => $data['amount'],
                'order_id' => $data['transaction_id'] ?? uniqid($this->config['merchant_prefix']),
                'currency' => $data['currency'] ?? 'BDT',
                'customer_name' => $data['customer_name'] ?? 'Customer Name',
                'customer_address' => $data['customer_address'] ?? 'Customer Address',
                'customer_phone' => $data['customer_phone'] ?? '01700000000',
                'customer_city' => $data['customer_city'] ?? 'Dhaka',
                'customer_post_code' => $data['customer_post_code'] ?? '1212',
                'client_ip' => $this->getClientIp(),
                // Optional fields Surjopay uses
                'value1' => $data['value1'] ?? null,
                'value2' => $data['value2'] ?? null,
                'value3' => $data['value3'] ?? null,
                'value4' => $data['value4'] ?? null,
            ];

            $response = $this->client->post($this->getBaseUrl() . '/api/secret-pay', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $auth['token']
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['checkout_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $payload['order_id'], $responseData['checkout_url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to get checkout URL', $payload['order_id'], null, $responseData);

        } catch (\Exception $e) {
            $this->logError('SurjoPay Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            // SurjoPay typically sends an 'order_id' via GET/POST param to the return URL
            if (!isset($data['order_id'])) {
                return $this->formatResponse(false, 'Missing order_id for verification');
            }

            $auth = $this->authenticate();

            $response = $this->client->post($this->getBaseUrl() . '/api/verification', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $auth['token']
                ],
                'json' => [
                    'order_id' => $data['order_id'],
                    'token' => $auth['token']
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Typically Surjopay returns an array, the first element represents the transaction
            $transaction = $responseData[0] ?? null;

            if ($transaction && isset($transaction['sp_code']) && $transaction['sp_code'] == '1000') {
                $this->fireSuccessEvent($data['order_id'], $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $data['order_id'], null, $responseData);
            }

            $this->fireFailedEvent($data['order_id'], $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $data['order_id'], null, $responseData);

        } catch (\Exception $e) {
            $this->logError('SurjoPay Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        // Note: Check Surjopay's actual refund endpoint, as it often varies or requires manual intervention
        return $this->formatResponse(false, 'SurjoPay automatic refunds not explicitly supported via standard API docs at this moment', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // Process SurjoPay IPN / Webhook request
        // Verify signature / data mapping based on actual webhook documentation.
        // For standard Surjopay IPN, you receive the same data as the success URL.
        return $this->verify($payload);
    }
}
