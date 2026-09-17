<?php

namespace PayBridge\Payment\Drivers;

class RocketDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        // DBBL typically uses ecom.dutchbanglabank.com for both test and live, with different merchant IDs/Terminals
        return $this->config['sandbox']
            ? 'https://ecomtest.dutchbanglabank.com/ecomws' // Example test URL
            : 'https://ecom.dutchbanglabank.com/ecomws';    // Example live URL
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('rkt_');

            // DBBL nexus gateway usually expects a SOAP or specific REST POST format. 
            // This is a modernized REST representation of the DBBL initiation process.
            $payload = [
                'merchantId' => $this->config['merchant_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'description' => $data['product_name'] ?? 'Order ' . $orderId,
                'clientIp' => $this->getClientIp(),
                'returnUrl' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/rocket/callback') . '?order_id=' . $orderId,
            ];

            // Some DBBL APIs require form URL encoded
            $response = $this->client->post($this->getBaseUrl() . '/createTransaction', [
                'form_params' => $payload
            ]);

            $responseStr = $response->getBody()->getContents();
            
            // DBBL often returns a plain string containing the Transaction ID (e.g., TRANSACTION_ID: 12345)
            // Or JSON depending on the exact version of the gateway they assigned
            $responseData = json_decode($responseStr, true);

            $checkoutUrl = null;
            $dbblTranId = null;

            if (is_array($responseData) && isset($responseData['transactionId'])) {
                $dbblTranId = $responseData['transactionId'];
                $checkoutUrl = $this->getBaseUrl() . '/paymentpage?trans_id=' . $dbblTranId;
            } elseif (strpos($responseStr, 'TRANSACTION_ID:') !== false) {
                // Handle older plain text response format
                $parts = explode(':', $responseStr);
                $dbblTranId = trim($parts[1] ?? '');
                if ($dbblTranId) {
                    $checkoutUrl = str_replace('ecomws', 'ecomm2', $this->getBaseUrl()) . '/ClientHandler?trans_id=' . urlencode($dbblTranId);
                }
            }

            if ($checkoutUrl) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $checkoutUrl, ['dbbl_tran_id' => $dbblTranId]);
            }

            return $this->formatResponse(false, 'Failed to get Rocket checkout URL', $orderId, null, ['raw' => $responseStr]);

        } catch (\Exception $e) {
            $this->logError('Rocket Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            $orderId = $data['order_id'] ?? null;
            // The DBBL gateway redirects back with trans_id
            $dbblTranId = $data['trans_id'] ?? null;

            if (!$dbblTranId) {
                return $this->formatResponse(false, 'Missing DBBL transaction ID for verification');
            }

            $response = $this->client->post($this->getBaseUrl() . '/verifyTransaction', [
                'form_params' => [
                    'merchantId' => $this->config['merchant_id'],
                    'transactionId' => $dbblTranId,
                    'clientIp' => $this->getClientIp()
                ]
            ]);

            $responseStr = $response->getBody()->getContents();
            $responseData = json_decode($responseStr, true);

            // Sometimes DBBL returns string result like "RESULT: OK"
            $statusOk = false;
            
            if (is_array($responseData) && isset($responseData['status']) && $responseData['status'] === 'ACCEPTED') {
                $statusOk = true;
            } elseif (strpos($responseStr, 'RESULT: OK') !== false) {
                $statusOk = true;
            }

            if ($statusOk) {
                $this->fireSuccessEvent($orderId, ['dbbl_tran_id' => $dbblTranId, 'raw' => $responseStr]);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, ['raw' => $responseStr]);
            }

            $this->fireFailedEvent($orderId, ['dbbl_tran_id' => $dbblTranId, 'raw' => $responseStr]);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, ['raw' => $responseStr]);

        } catch (\Exception $e) {
            $this->logError('Rocket Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'Rocket/DBBL refund API generally restricted or requires manual processing.', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // DBBL generally does not use async webhooks in the modern sense, they rely on the verify step
        // after user redirection. 
        $orderId = $payload['order_id'] ?? null;
        
        if (isset($payload['RESULT']) && $payload['RESULT'] === 'OK') {
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', $orderId, null, $payload);
    }
}
