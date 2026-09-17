<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class StripeDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->config['secret_key'],
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/x-www-form-urlencoded'
        ];
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('str_');
            
            // Stripe expects form-urlencoded for its API, not JSON
            $payload = [
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($data['currency'] ?? 'usd'),
                            'product_data' => [
                                'name' => $data['product_name'] ?? 'Payment for Order ' . $orderId,
                            ],
                            // Stripe expects amount in cents
                            'unit_amount' => (int) ($data['amount'] * 100),
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => $this->resolveUrl($this->config['success_url'] ?? '/payment/stripe/success') . '?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/stripe/cancel') . '?order_id=' . $orderId,
                'client_reference_id' => $orderId,
                'customer_email' => $data['customer_email'] ?? null,
            ];

            // Build query string manually to handle nested arrays for x-www-form-urlencoded
            $response = $this->client->post($this->getBaseUrl() . '/checkout/sessions', [
                'headers' => $this->getHeaders(),
                'form_params' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['url'])) {
                return $this->formatResponse(true, 'Stripe checkout session created', $orderId, $responseData['url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to create Stripe checkout session', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Stripe Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            if (!isset($data['session_id'])) {
                return $this->formatResponse(false, 'Missing Stripe session_id for verification');
            }

            $response = $this->client->get($this->getBaseUrl() . '/checkout/sessions/' . $data['session_id'], [
                'headers' => $this->getHeaders()
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            $orderId = $data['order_id'] ?? ($responseData['client_reference_id'] ?? null);

            if (isset($responseData['payment_status']) && $responseData['payment_status'] === 'paid') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment pending or failed according to Stripe', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Stripe Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        try {
            $response = $this->client->post($this->getBaseUrl() . '/refunds', [
                'headers' => $this->getHeaders(),
                'form_params' => [
                    'payment_intent' => $transactionId 
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['status']) && $responseData['status'] === 'succeeded') {
                return $this->formatResponse(true, 'Refund successful', $transactionId, null, $responseData);
            }

            return $this->formatResponse(false, 'Refund failed', $transactionId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Stripe Refund Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function webhook(array $payload): array
    {
        // For a true integration, you should verify the stripe-signature header using your webhook_secret
        // This is a simplified webhook handler.
        if (isset($payload['type']) && $payload['type'] === 'checkout.session.completed') {
            $session = $payload['data']['object'];
            $orderId = $session['client_reference_id'];
            
            if ($session['payment_status'] === 'paid') {
                $this->fireSuccessEvent($orderId, $payload);
                return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
            }
        }

        return $this->formatResponse(false, 'Unhandled webhook event type', null, null, $payload);
    }
}
