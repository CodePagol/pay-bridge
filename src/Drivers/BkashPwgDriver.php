<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class BkashPwgDriver extends AbstractGatewayDriver
{
    private string $sandboxUrl = 'https://pay.sandbox.bka.sh/v1.2.0-beta';
    private string $liveUrl = 'https://pay.bka.sh/v1.2.0-beta';

    private function getBaseUrl(): string
    {
        return $this->isSandbox() ? $this->sandboxUrl : $this->liveUrl;
    }

    /**
     * PWG requires generating a token.
     */
    private function getToken(): string
    {
        $url = $this->getBaseUrl() . '/tokenized/checkout/token/grant';
        
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

        throw new PaymentException("bKash PWG: Could not generate token. " . ($response['statusMessage'] ?? 'Unknown error'));
    }

    public function pay(array $data): array
    {
        try {
            $transactionId = $data['transaction_id'] ?? $this->generateUuid();
            $token = $this->getToken();
            $url = $this->getBaseUrl() . '/tokenized/checkout/create';

            $postData = [
                'mode' => '0011',
                'payerReference' => ' ',
                'callbackURL' => $this->resolveUrl($this->config['callback_url'] ?? '/payment/bkash_pwg/callback'),
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
                    $response['bkashURL'], // Redirect URL for PWG
                    $response
                );
            }

            return $this->formatResponse(
                false,
                $response['statusMessage'] ?? 'Failed to create payment via bKash PWG',
                $transactionId,
                null,
                $response
            );
        } catch (\Throwable $e) {
            $this->logError('bKash PWG pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage(), $data['transaction_id'] ?? null);
        }
    }

    public function verify(array $data): array
    {
        try {
            if (!isset($data['paymentID'])) {
                return $this->formatResponse(false, 'paymentID is required for verify.');
            }

            $token = $this->getToken();
            $url = $this->getBaseUrl() . '/tokenized/checkout/execute';

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
            $this->logError('bKash PWG verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        return $this->formatResponse(false, 'bKash PWG refund pending logic', $transactionId);
    }

    public function webhook(array $payload): array
    {
        return $this->formatResponse(false, 'bKash webhook pending validation logic');
    }
}
