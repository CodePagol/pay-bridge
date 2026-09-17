<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class SSLCommerzDriver extends AbstractGatewayDriver
{
    private string $sandboxUrl = 'https://sandbox.sslcommerz.com';
    private string $liveUrl = 'https://securepay.sslcommerz.com';

    private function getBaseUrl(): string
    {
        return $this->isSandbox() ? $this->sandboxUrl : $this->liveUrl;
    }

    public function pay(array $data): array
    {
        try {
            $tranId = $data['transaction_id'] ?? $this->generateUuid();

            $postData = [
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'total_amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'BDT',
                'tran_id' => $tranId,
                'success_url' => $this->resolveUrl($this->config['success_url'] ?? '/payment/sslcommerz/success'),
                'fail_url' => $this->resolveUrl($this->config['fail_url'] ?? '/payment/sslcommerz/fail'),
                'cancel_url' => $this->resolveUrl($this->config['cancel_url'] ?? '/payment/sslcommerz/cancel'),
                'cus_name' => $data['customer_name'] ?? 'John Doe',
                'cus_email' => $data['customer_email'] ?? 'test@example.com',
                'cus_add1' => $data['customer_address'] ?? 'Dhaka',
                'cus_city' => $data['customer_city'] ?? 'Dhaka',
                'cus_state' => $data['customer_state'] ?? 'Dhaka',
                'cus_postcode' => $data['customer_postcode'] ?? '1000',
                'cus_country' => $data['customer_country'] ?? 'Bangladesh',
                'cus_phone' => $data['customer_phone'] ?? '01711111111',
                'shipping_method' => 'NO',
                'product_name' => $data['product_name'] ?? 'Payment',
                'product_category' => 'General',
                'product_profile' => 'general',
            ];

            // If specified, SSLCommerz will pre-filter/open specific payment channels (e.g., 'bkash', 'visacard', 'mobilebank')
            if (!empty($data['multi_card_name'])) {
                $postData['multi_card_name'] = $data['multi_card_name'];
            }

            $response = $this->postRequest($this->getBaseUrl() . '/gwprocess/v4/api.php', $postData);

            if (isset($response['status']) && $response['status'] === 'SUCCESS') {
                return $this->formatResponse(
                    true,
                    'Payment initiated successfully',
                    $tranId,
                    $response['GatewayPageURL'] ?? null,
                    $response
                );
            }

            return $this->formatResponse(
                false,
                $response['failedreason'] ?? 'Failed to initiate payment',
                $tranId,
                null,
                $response
            );
        } catch (\Throwable $e) {
            $this->logError('SSLCommerz pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage(), $data['transaction_id'] ?? null);
        }
    }

    public function verify(array $data): array
    {
        try {
            if (!isset($data['val_id'])) {
                return $this->formatResponse(false, 'val_id is required to verify SSLCommerz payment.', $data['tran_id'] ?? null);
            }

            $url = $this->getBaseUrl() . "/validator/api/validationserverAPI.php?" . http_build_query([
                'val_id' => $data['val_id'],
                'store_id' => $this->config['store_id'] ?? '',
                'store_passwd' => $this->config['store_password'] ?? '',
                'v' => 1,
                'format' => 'json'
            ]);

            $response = $this->postRequest($url, []);

            if (isset($response['status']) && ($response['status'] === 'VALID' || $response['status'] === 'VALIDATED')) {
                $verifiedAmount = isset($response['amount']) ? (float)$response['amount'] : null;
                $verifiedCurrency = $response['currency'] ?? 'BDT';

                return $this->formatResponse(
                    true,
                    'Payment verified successfully',
                    $response['tran_id'] ?? ($data['tran_id'] ?? null),
                    null,
                    $response,
                    $verifiedAmount,
                    $verifiedCurrency
                );
            }

            return $this->formatResponse(false, 'Verification failed', $data['tran_id'] ?? null, null, $response);
        } catch (\Throwable $e) {
            $this->logError('SSLCommerz verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage(), $data['tran_id'] ?? null);
        }
    }

    public function refund(string $transactionId): array
    {
        // SSLCommerz typically handles refund request manually or via a different dedicated refund API.
        // A placeholder for the standard structure:
        return $this->formatResponse(false, 'Refund method not fully implemented in standard API sandbox', $transactionId);
    }

    public function webhook(array $payload): array
    {
        // Hash validation according to SSLCommerz IPN documentation
        if (isset($payload['verify_sign']) && isset($payload['verify_key'])) {
            $pre_define_key = explode(',', $payload['verify_key']);
            $new_data = [];
            foreach ($pre_define_key as $value) {
                if (isset($payload[$value])) {
                    $new_data[$value] = ($payload[$value]);
                }
            }
            $new_data['store_passwd'] = md5($this->config['store_password']);
            ksort($new_data);
            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . '=' . $value . '&';
            }
            $hash_string = rtrim($hash_string, '&');

            // Prevent timing attacks & type-juggling by using constant-time comparison
            if (hash_equals(md5($hash_string), (string)$payload['verify_sign'])) {
                $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;
                $currency = $payload['currency'] ?? 'BDT';

                return $this->formatResponse(true, 'Webhook verified', $payload['tran_id'], null, $payload, $amount, $currency);
            }
        }

        return $this->formatResponse(false, 'Webhook signature mismatch', $payload['tran_id'] ?? null, null, $payload);
    }
}
