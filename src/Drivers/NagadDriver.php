<?php

namespace PayBridge\Payment\Drivers;

class NagadDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->config['sandbox']
            ? 'https://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'
            : 'https://api.mynagad.com/api/dfs';
    }

    /**
     * Get properly formatted private key string.
     */
    protected function getPrivateKey(): string
    {
        $key = trim($this->config['private_key'] ?? '');
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }
        return "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
    }

    /**
     * Get properly formatted public key string.
     */
    protected function getPublicKey(): string
    {
        $key = trim($this->config['public_key'] ?? '');
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }
        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    /**
     * Helper to generate RSA Signature
     */
    protected function generateSignature(string $data): string
    {
        $privateKey = $this->getPrivateKey();
        $signature = '';
        @openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * Helper to verify RSA Signature
     */
    protected function verifySignature(string $data, string $signature): bool
    {
        $publicKey = $this->getPublicKey();
        $binarySignature = base64_decode($signature);
        return @openssl_verify($data, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Helper to Encrypt Data with RSA Public Key
     */
    protected function encryptDataWithPublicKey(string $data): string
    {
        $publicKey = $this->getPublicKey();
        $encrypted = '';
        @openssl_public_encrypt($data, $encrypted, $publicKey);
        return base64_encode($encrypted);
    }

    /**
     * Helper to Decrypt Data with RSA Private Key
     */
    protected function decryptDataWithPrivateKey(string $data): string
    {
        $privateKey = $this->getPrivateKey();
        $decrypted = '';
        @openssl_private_decrypt(base64_decode($data), $decrypted, $privateKey);
        return $decrypted ?? '';
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('nagad_');
            $dateTime = date('YmdHis');

            // Phase 1: Initialize Payment
            $initData = [
                'merchantId' => $this->config['merchant_id'],
                'datetime' => $dateTime,
                'orderId' => $orderId,
                'challenge' => bin2hex(random_bytes(20))
            ];

            $initDataStr = json_encode($initData);
            $signature = $this->generateSignature($initDataStr);
            $sensitiveData = $this->encryptDataWithPublicKey($initDataStr);

            $initPayload = [
                'dateTime' => $dateTime,
                'sensitiveData' => $sensitiveData,
                'signature' => $signature
            ];

            $initResponse = $this->client->post($this->getBaseUrl() . '/check-out/initialize/' . $this->config['merchant_id'] . '/' . $orderId, [
                'headers' => [
                    'X-KM-IP-V4' => $this->getClientIp(),
                    'X-KM-Api-Version' => 'v-0.2.0',
                    'Content-Type' => 'application/json'
                ],
                'json' => $initPayload
            ]);

            $initResponseData = json_decode($initResponse->getBody()->getContents(), true);

            if (!isset($initResponseData['sensitiveData'])) {
                return $this->formatResponse(false, 'Nagad initialization failed', $orderId, null, $initResponseData);
            }

            // Phase 2: Complete Initialization
            $decryptedSensitiveData = json_decode($this->decryptDataWithPrivateKey($initResponseData['sensitiveData']), true);

            if (!isset($decryptedSensitiveData['paymentReferenceId'])) {
                return $this->formatResponse(false, 'Failed to extract Payment Reference ID', $orderId, null, $initResponseData);
            }

            $paymentRefId = $decryptedSensitiveData['paymentReferenceId'];
            $challenge = $decryptedSensitiveData['challenge'];

            $completeData = [
                'merchantId' => $this->config['merchant_id'],
                'orderId' => $orderId,
                'currencyCode' => '050', // BDT currency code locally mapped
                'amount' => number_format((float)$data['amount'], 2, '.', ''),
                'challenge' => $challenge
            ];

            $completeDataStr = json_encode($completeData);
            $completeSignature = $this->generateSignature($completeDataStr);
            $completeSensitiveData = $this->encryptDataWithPublicKey($completeDataStr);

            $completePayload = [
                'sensitiveData' => $completeSensitiveData,
                'signature' => $completeSignature,
                'merchantCallbackURL' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/nagad/callback'),
                'additionalMerchantInfo' => [
                    'customerName' => $data['customer_name'] ?? 'Customer',
                    'customerPhone' => $data['customer_phone'] ?? '01700000000'
                ]
            ];

            $completeResponse = $this->client->post($this->getBaseUrl() . '/check-out/complete/' . $paymentRefId, [
                'headers' => [
                    'X-KM-IP-V4' => $this->getClientIp(),
                    'X-KM-Api-Version' => 'v-0.2.0',
                    'Content-Type' => 'application/json'
                ],
                'json' => $completePayload
            ]);

            $completeResponseData = json_decode($completeResponse->getBody()->getContents(), true);

            if (isset($completeResponseData['status']) && $completeResponseData['status'] === 'Success' && isset($completeResponseData['callBackUrl'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $completeResponseData['callBackUrl'], $completeResponseData);
            }

            return $this->formatResponse(false, 'Failed to get Nagad checkout URL', $orderId, null, $completeResponseData);

        } catch (\Exception $e) {
            $this->logError('Nagad Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            // Nagad sends payment_ref_id or paymentReferenceId on success redirect
            $paymentRefId = $data['payment_ref_id'] ?? ($data['paymentReferenceId'] ?? null);

            if (!$paymentRefId) {
                return $this->formatResponse(false, 'Missing payment reference ID for verification');
            }

            $response = $this->client->get($this->getBaseUrl() . '/verify/payment/' . $paymentRefId, [
                'headers' => [
                    'X-KM-IP-V4' => $this->getClientIp(),
                    'X-KM-Api-Version' => 'v-0.2.0',
                    'Content-Type' => 'application/json'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $orderId = $responseData['orderId'] ?? null;

            if (isset($responseData['status']) && $responseData['status'] === 'Success') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Nagad Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'Nagad refund requires explicit merchant console action or a dedicated refund API structure normally handled manually.', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // Nagad typically communicates status changes via callbacks/IPN if configured in merchant portal
        $orderId = $payload['orderId'] ?? null;
        if (isset($payload['status']) && $payload['status'] === 'Success') {
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed successfully', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook event ignored or failed', $orderId, null, $payload);
    }
}
