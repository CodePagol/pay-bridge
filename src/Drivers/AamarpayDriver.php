<?php

namespace PayBridge\Payment\Drivers;

class AamarpayDriver extends AbstractGatewayDriver
{
    protected function getBaseUrl(): string
    {
        return $this->config['sandbox']
            ? 'https://sandbox.aamarpay.com'
            : 'https://secure.aamarpay.com';
    }

    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? uniqid('aamar_');

            $payload = [
                'store_id' => $this->config['store_id'],
                'signature_key' => $this->config['signature_key'],
                'tran_id' => $orderId,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'desc' => $data['product_name'] ?? 'Payment for Order ' . $orderId,
                'cus_name' => $data['customer_name'] ?? 'Customer Name',
                'cus_email' => $data['customer_email'] ?? 'customer@example.com',
                'cus_add1' => $data['customer_address'] ?? 'Customer Address',
                'cus_add2' => 'N/A',
                'cus_city' => $data['customer_city'] ?? 'Dhaka',
                'cus_state' => $data['customer_state'] ?? 'Dhaka',
                'cus_postcode' => $data['customer_post_code'] ?? '1212',
                'cus_country' => $data['customer_country'] ?? 'Bangladesh',
                'cus_phone' => $data['customer_phone'] ?? '01711111111',
                'type' => 'json',
                'success_url' => $this->resolveUrl($this->config['success_url'] ?? '/payment/aamarpay/success'),
                'fail_url' => $this->resolveUrl($this->config['fail_url'] ?? '/payment/aamarpay/fail'),
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/aamarpay/cancel'),
            ];

            // Aamarpay uses JSON format for its modern API request
            $response = $this->client->post($this->getBaseUrl() . '/jsonpost.php', [
                'json' => $payload
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['result']) && $responseData['result'] === 'true' && isset($responseData['payment_url'])) {
                return $this->formatResponse(true, 'Payment initiated', $orderId, $responseData['payment_url'], $responseData);
            }

            return $this->formatResponse(false, 'Failed to get Aamarpay checkout URL', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('AamarPay Pay Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function verify(array $data): array
    {
        try {
            // Aamarpay typically posts 'pay_status' and 'mer_txnid' to the success URL
            $orderId = $data['mer_txnid'] ?? ($data['tran_id'] ?? null);

            if (!$orderId) {
                return $this->formatResponse(false, 'Missing transaction ID for verification');
            }

            $response = $this->client->post($this->getBaseUrl() . '/api/v1/trxcheck/request.php', [
                'form_params' => [
                    'request_id' => $orderId,
                    'store_id' => $this->config['store_id'],
                    'signature_key' => $this->config['signature_key'],
                    'type' => 'json'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['pay_status']) && $responseData['pay_status'] === 'Successful') {
                $this->fireSuccessEvent($orderId, $responseData);
                return $this->formatResponse(true, 'Payment verified successfully', $orderId, null, $responseData);
            }

            $this->fireFailedEvent($orderId, $responseData);
            return $this->formatResponse(false, 'Payment verification failed', $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('AamarPay Verify Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    public function refund(string $transactionId): array
    {
        // Aamarpay refund usually requires talking to your account manager or a separate locked API
        return $this->formatResponse(false, 'Aamarpay refund API requires special merchant confirmation', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // Aamarpay IPN sends POST data similar to success URL
        if (isset($payload['pay_status']) && $payload['pay_status'] === 'Successful') {
            $orderId = $payload['mer_txnid'] ?? ($payload['tran_id'] ?? null);
            $this->fireSuccessEvent($orderId, $payload);
            return $this->formatResponse(true, 'Webhook processed', $orderId, null, $payload);
        }

        return $this->formatResponse(false, 'Webhook ignored or failed', null, null, $payload);
    }
}
