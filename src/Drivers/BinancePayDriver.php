<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;
use Illuminate\Support\Str;

class BinancePayDriver extends AbstractGatewayDriver
{
    private string $baseUrl = 'https://bpay.binanceapi.com';

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Generate required authentication headers for Binance Pay API.
     */
    protected function generateHeaders(string $jsonBody): array
    {
        $timestamp = round(microtime(true) * 1000);
        $nonce = Str::random(32);
        $apiKey = $this->config['api_key'] ?? '';
        $secretKey = $this->config['secret_key'] ?? '';

        $payloadToSign = $timestamp . "\n" . $nonce . "\n" . $jsonBody . "\n";
        $signature = strtoupper(hash_hmac('sha512', $payloadToSign, $secretKey));

        return [
            'Content-Type'               => 'application/json',
            'BinancePay-Timestamp'      => (string)$timestamp,
            'BinancePay-Nonce'          => $nonce,
            'BinancePay-Certificate-SN' => $apiKey,
            'BinancePay-Signature'      => $signature,
        ];
    }

    /**
     * Initiate a payment order via Binance Pay.
     */
    public function pay(array $data): array
    {
        try {
            $orderId = $data['transaction_id'] ?? $this->generateUuid();

            $body = [
                'env' => [
                    'terminalType' => $data['terminal_type'] ?? 'WEB',
                ],
                'merchantTradeNo' => $orderId,
                'orderAmount'     => (float)$data['amount'],
                'currency'        => strtoupper($data['currency'] ?? 'USDT'),
                'goods'           => [
                    'goodsType'        => $data['goods_type'] ?? '02',
                    'goodsCategory'    => $data['goods_category'] ?? 'Z000',
                    'referenceGoodsId' => $data['product_id'] ?? $orderId,
                    'goodsName'        => $data['product_name'] ?? ('Payment ' . $orderId),
                    'goodsDetail'      => $data['product_detail'] ?? ($data['product_name'] ?? 'Payment for order ' . $orderId),
                ],
                'returnUrl' => $this->resolveUrl($data['return_url'] ?? ($this->config['return_url'] ?? '/payment/binance/return')),
                'cancelUrl' => $this->resolveUrl($data['cancel_url'] ?? ($this->config['cancel_url'] ?? '/payment/binance/cancel')),
            ];

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers = $this->generateHeaders($jsonBody);

            $response = $this->client->post($this->getBaseUrl() . '/binancepay/openapi/v2/order', [
                'headers' => $headers,
                'body'    => $jsonBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS' && isset($responseData['data'])) {
                $checkoutUrl = $responseData['data']['universalUrl'] 
                    ?? $responseData['data']['checkoutUrl'] 
                    ?? $responseData['data']['deeplink'] 
                    ?? null;

                return $this->formatResponse(
                    true,
                    'Binance Pay order created successfully',
                    $orderId,
                    $checkoutUrl,
                    $responseData
                );
            }

            $errorMessage = $responseData['errorMessage'] ?? 'Failed to create Binance Pay order';
            return $this->formatResponse(false, $errorMessage, $orderId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Binance Pay pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Query order status from Binance Pay.
     */
    public function verify(array $data): array
    {
        try {
            $merchantTradeNo = $data['merchantTradeNo'] ?? ($data['transaction_id'] ?? null);

            if (!$merchantTradeNo) {
                return $this->formatResponse(false, 'Missing merchantTradeNo / transaction_id for Binance Pay query');
            }

            $body = [
                'merchantTradeNo' => $merchantTradeNo,
            ];

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers = $this->generateHeaders($jsonBody);

            $response = $this->client->post($this->getBaseUrl() . '/binancepay/openapi/v2/order/query', [
                'headers' => $headers,
                'body'    => $jsonBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                $orderData = $responseData['data'] ?? [];
                $orderStatus = $orderData['status'] ?? null;
                $tradeNo = $orderData['tradeNo'] ?? null;

                if ($orderStatus === 'PAID') {
                    $amount = isset($orderData['orderAmount']) ? (float)$orderData['orderAmount'] : null;
                    $currency = $orderData['currency'] ?? 'USDT';
                    return $this->formatResponse(true, 'Payment verified successfully', $tradeNo, null, $responseData, $amount, $currency);
                }

                return $this->formatResponse(false, "Payment status is {$orderStatus}", $tradeNo, null, $responseData);
            }

            $errorMsg = $responseData['errorMessage'] ?? 'Failed to verify Binance Pay order';
            return $this->formatResponse(false, $errorMsg, $merchantTradeNo, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Binance Pay verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Refund a Binance Pay transaction.
     */
    public function refund(string $transactionId): array
    {
        try {
            $refundRequestId = $this->generateUuid();

            $body = [
                'refundRequestId' => $refundRequestId,
                'prepardId'       => $transactionId,
                'refundReason'    => 'Customer refund request',
            ];

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers = $this->generateHeaders($jsonBody);

            $response = $this->client->post($this->getBaseUrl() . '/binancepay/openapi/v1/refund/order', [
                'headers' => $headers,
                'body'    => $jsonBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true) ?? [];

            if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                return $this->formatResponse(true, 'Binance Pay refund initiated', $transactionId, null, $responseData);
            }

            $errorMsg = $responseData['errorMessage'] ?? 'Failed to refund Binance Pay transaction';
            return $this->formatResponse(false, $errorMsg, $transactionId, null, $responseData);

        } catch (\Exception $e) {
            $this->logError('Binance Pay refund() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Process incoming webhook notification from Binance Pay.
     */
    public function webhook(array $payload): array
    {
        try {
            // Cryptographic Webhook Signature Verification
            $secretKey = $this->config['secret_key'] ?? null;
            if (empty($secretKey)) {
                $this->logCritical('Binance Pay Webhook Error: Secret key is not configured. Webhook rejected for security.');
                return $this->formatResponse(false, 'Binance Pay secret key not configured on server.', null, null, $payload);
            }

            $timestamp = $this->getHeader('binancepay-timestamp') ?? $this->getHeader('BinancePay-Timestamp');
            $nonce     = $this->getHeader('binancepay-nonce') ?? $this->getHeader('BinancePay-Nonce');
            $signature = $this->getHeader('binancepay-signature') ?? $this->getHeader('BinancePay-Signature');

            if ($timestamp && $nonce && $signature) {
                $rawBody = $this->getRawBody();
                $payloadToSign = $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n";
                $expectedSig = strtoupper(hash_hmac('sha512', $payloadToSign, $secretKey));

                if (!hash_equals($expectedSig, $signature)) {
                    $this->logWarning('Binance Pay Webhook Error: Webhook signature mismatch.');
                    return $this->formatResponse(false, 'Binance Pay Webhook: Invalid signature', null, null, $payload);
                }
            }

            // Check status in Binance webhook notification
            $bizStatus = $payload['bizStatus'] ?? null;
            $data = is_string($payload['data'] ?? null) ? (json_decode($payload['data'], true) ?: []) : ($payload['data'] ?? []);
            $merchantTradeNo = $data['merchantTradeNo'] ?? null;
            $amount = isset($data['orderAmount']) ? (float)$data['orderAmount'] : null;
            $currency = $data['currency'] ?? 'USDT';

            if ($bizStatus === 'PAY_SUCCESS') {
                return $this->formatResponse(true, 'Binance Pay Webhook: Payment Successful', $merchantTradeNo, null, $payload, $amount, $currency);
            }

            return $this->formatResponse(false, 'Binance Pay Webhook: Unhandled or failed status', $merchantTradeNo, null, $payload);

        } catch (\Exception $e) {
            $this->logError('Binance Pay webhook() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }
}
