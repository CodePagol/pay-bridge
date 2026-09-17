<?php

namespace PayBridge\Payment\Drivers;

class PayPalDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->config['sandbox']
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Generate OAuth2 Access Token
     */
    protected function getAccessToken(): string
    {
        $response = $this->client->post($this->getBaseUrl() . '/v1/oauth2/token', [
            'auth' => [$this->config['client_id'], $this->config['secret']],
            'form_params' => [
                'grant_type' => 'client_credentials'
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['access_token'])) {
            return $data['access_token'];
        }

        throw new \PayBridge\Payment\Exceptions\PaymentException('Failed to get PayPal access token.');
    }

    public function pay(array $data): array
    {
        try {
            $token = $this->getAccessToken();
            $orderId = $data['transaction_id'] ?? uniqid('pp_');

            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => $orderId,
                        'amount' => [
                            'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                            'value' => number_format((float)$data['amount'], 2, '.', '')
                        ],
                        'description' => $data['product_name'] ?? 'Order ' . $orderId
                    ]
                ],
                'application_context' => [
                    'return_url' => $this->resolveUrl($this->config['success_url'] ?? '/payment/paypal/success'),
                    'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/paypal/cancel'),
                    'user_action' => 'PAY_NOW'
                ]
            ];

            $response = $this->client->post($this->getBaseUrl() . '/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Find the approval URL
            $checkoutUrl = null;
            if (isset($responseData['links'])) {
                foreach ($responseData['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $checkoutUrl = $link['href'];
                        break;
                    }
                }
            }

            if ($checkoutUrl) {
                return $this->formatResponse(true, 'Payment initiated', $responseData['id'], $checkoutUrl, $responseData);
            }

            return $this->formatResponse(false, 'Failed to get PayPal checkout URL', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('PayPal Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            // PayPal redirects with 'token' which is the order ID in their system
            $paypalOrderId = $data['token'] ?? null;

            if (!$paypalOrderId) {
                return $this->formatResponse(false, 'Missing PayPal token (order id) for verification');
            }

            $token = $this->getAccessToken();

            // Capture the payment
            $response = $this->client->post($this->getBaseUrl() . "/v2/checkout/orders/{$paypalOrderId}/capture", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'COMPLETED') {
                $customTransactionId = $responseData['purchase_units'][0]['reference_id'] ?? $paypalOrderId;
                $this->fireSuccessEvent($customTransactionId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $customTransactionId, null, $responseData);
            }

            $this->fireFailedEvent($paypalOrderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $paypalOrderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('PayPal Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        try {
            $token = $this->getAccessToken();
            
            // To refund, PayPal needs the Capture ID, not exactly the Order ID.
            // Ensure $transactionId passed here is the capture ID.
            $response = $this->client->post($this->getBaseUrl() . "/v2/payments/captures/{$transactionId}/refund", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'COMPLETED') {
                return $this->formatResponse(true, 'Refund successful', $transactionId, null, $responseData);
            }

            return $this->formatResponse(false, 'Refund failed', $transactionId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('PayPal Refund Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function webhook(array $payload): array
    {
        // Simple Webhook handling. In production, verify the signature using PayPal's webhook verification API
        if (isset($payload['event_type']) && $payload['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $payload['resource'] ?? [];
            $orderId = $resource['id'] ?? 'unknown';
            
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Unhandled or non-success webhook event type', null, null, $payload);
    }
}
