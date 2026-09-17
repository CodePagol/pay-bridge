<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class BkashTokenizeDriver extends AbstractGatewayDriver
{
    private string $sandboxUrl = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized';
    private string $liveUrl = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized';

    private function getBaseUrl(): string
    {
        return $this->isSandbox() ? $this->sandboxUrl : $this->liveUrl;
    }

    /**
     * Get bKash Grant Token
     */
    private function getGrantToken(): string
    {
        $url = $this->getBaseUrl() . '/checkout/token/grant';
        
        $postData = [
            'app_key' => $this->config['app_key'],
            'app_secret' => $this->config['app_secret'],
        ];

        $headers = [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ];

        $response = $this->postJsonRequest($url, $postData, $headers);

        if (isset($response['id_token'])) {
            return $response['id_token'];
        }

        throw new PaymentException("bKash Tokenize: Could not generate grant token. " . ($response['statusMessage'] ?? 'Unknown error'));
    }

    public function pay(array $data): array
    {
        try {
            $transactionId = $data['transaction_id'] ?? $this->generateUuid();

            // 1. Get Grant Token
            $token = $this->getGrantToken();

            // 2. Create Payment
            $url = $this->getBaseUrl() . '/checkout/create';

            $postData = [
                'mode' => '0011',
                'payerReference' => ' ',
                'callbackURL' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/bkash_tokenize/callback'),
                'amount' => $data['amount'],
                'currency' => 'BDT',
                'intent' => 'sale',
                'merchantInvoiceNumber' => $transactionId,
            ];

            $headers = [
                'Authorization' => $token,
                'X-APP-Key' => $this->config['app_key'] ?? '',
            ];

            $response = $this->postJsonRequest($url, $postData, $headers);

            if (isset($response['statusCode']) && $response['statusCode'] === '0000' && isset($response['bkashURL'])) {
                return $this->formatResponse(
                    true,
                    'Payment created successfully',
                    $transactionId,
                    $response['bkashURL'],
                    $response
                );
            }

            return $this->formatResponse(
                false,
                $response['statusMessage'] ?? 'Failed to create bKash tokenized payment',
                $transactionId,
                null,
                $response
            );
        } catch (\Throwable $e) {
            $this->logError('bKash Tokenize pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage(), $data['transaction_id'] ?? null);
        }
    }

    public function verify(array $data): array
    {
        try {
            if (!isset($data['paymentID']) || !isset($data['status'])) {
                return $this->formatResponse(false, 'paymentID and status are required for verify.');
            }

            if ($data['status'] !== 'success') {
                return $this->formatResponse(false, 'Status returned was not success: ' . $data['status'], null, null, $data);
            }

            $token = $this->getGrantToken();
            $url = $this->getBaseUrl() . '/checkout/execute';

            $postData = [
                'paymentID' => $data['paymentID'],
            ];

            $headers = [
                'Authorization' => $token,
                'X-APP-Key' => $this->config['app_key'] ?? '',
            ];

            $response = $this->postJsonRequest($url, $postData, $headers);

            if (isset($response['statusCode']) && $response['statusCode'] === '0000' && isset($response['trxID'])) {
                return $this->formatResponse(true, 'Payment execution successful', $response['trxID'], null, $response);
            }

            return $this->formatResponse(false, 'Verification failed: ' . ($response['statusMessage'] ?? 'Unknown error'), null, null, $response);
        } catch (\Throwable $e) {
            $this->logError('bKash Tokenize verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'bKash Tokenize refund requires further implementation logic', $transactionId);
    }

    public function webhook(array $payload): array
    {
        return $this->formatResponse(false, 'bKash webhook pending validation logic');
    }
}
