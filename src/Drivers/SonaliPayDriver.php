<?php

namespace PayBridge\Payment\Drivers;

class SonaliPayDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://spg.sonalibank.com.bd:7070'
            : 'https://spg.sonalibank.com.bd:8080';
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('spg_');
            
            // Sonali Bank typically uses Form POST redirect with specific fields
            $payload = [
                'merchant_id' => $this->config['merchant_id'],
                'transaction_id' => $orderId,
                'amount' => $data['amount'],
                'return_url' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/sonalipay/callback'),
                // Additional fields as required by SPG docs...
            ];

            // In some older bank APIs like SPG, you don't always get a JSON URL back immediately.
            // Often, you have to POST the data directly to their endpoint from the frontend.
            // However, assuming they have a modern endpoint that returns a checkout link:

            $response = $this->client->post($this->getBaseUrl() . '/api/pay', [
                'form_params' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Assuming a standard format return for SDKs
            if (isset($responseData['checkout_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $responseData['checkout_url'], $responseData);
            }

            // SonaliPay requires a direct form POST from the browser to their endpoint.
            // Return the endpoint + payload so the application can render a self-submitting HTML form.
            return $this->formatResponse(false, 'SonaliPay requires a direct browser form POST to the gateway. Use direct_post_endpoint and direct_post_payload from raw_response to render a self-submitting form.', $orderId, null, [
                'direct_post_required'  => true,
                'direct_post_endpoint'  => $this->getBaseUrl() . '/spg/pay.php',
                'direct_post_payload'   => $payload,
            ]);

        } catch (\Throwable $e) {
            $this->logError('SonaliPay Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? null;

            if (!$orderId) {
                return $this->formatResponse(false, 'Missing transaction ID for SPG verification');
            }

            // SPG often requires an XML payload for verification
            $xml = '<?xml version="1.0" encoding="utf-8"?>';
            $xml .= '<PaymentVerificationReq>';
            $xml .= '<MerchantId>' . htmlspecialchars($this->config['merchant_id']) . '</MerchantId>';
            $xml .= '<TransactionId>' . htmlspecialchars($orderId) . '</TransactionId>';
            $xml .= '</PaymentVerificationReq>';

            $response = $this->client->post($this->getBaseUrl() . '/api/verify', [
                'headers' => [
                    'Content-Type' => 'text/xml'
                ],
                'body' => $xml
            ]);

            $responseStr = $response->getBody()->getContents();
            
            // Basic XML parsing
            $responseData = [];
            if (strpos($responseStr, '<Status>SUCCESS</Status>') !== false) {
                $responseData['status'] = 'SUCCESS';
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $responseData['status'] = 'FAILED';
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Throwable $e) {
            $this->logError('SonaliPay Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'SonaliPay refund API generally requires manual reconciliation', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // SonaliPay might send XML or POST data
        $orderId = $payload['transaction_id'] ?? null;

        if (isset($payload['status']) && strtoupper($payload['status']) === 'SUCCESS') {
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', $orderId, null, $payload);
    }
}
