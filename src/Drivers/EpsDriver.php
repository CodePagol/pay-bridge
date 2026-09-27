<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class EpsDriver extends AbstractGatewayDriver
{
    private string $sandboxBaseUrl = 'https://sandboxpgapi.eps.com.bd';
    private string $liveBaseUrl = 'https://pgapi.eps.com.bd';

    /**
     * Resolve base URL for EPS API endpoints.
     */
    protected function getBaseUrl(): string
    {
        if (!empty($this->config['base_url'])) {
            return rtrim($this->config['base_url'], '/');
        }

        if (!empty($this->config['EPSBaseURL'])) {
            return rtrim($this->config['EPSBaseURL'], '/');
        }

        return $this->isSandbox() ? $this->sandboxBaseUrl : $this->liveBaseUrl;
    }

    /**
     * Get Merchant ID supporting both standard and official EPS key names.
     */
    protected function getMerchantId(): string
    {
        return (string)(
            $this->config['merchant_id'] 
            ?? $this->config['EPSMerchentID'] 
            ?? $this->config['merchent_id'] 
            ?? ''
        );
    }

    /**
     * Get Store ID supporting both standard and official EPS key names.
     */
    protected function getStoreId(): string
    {
        return (string)(
            $this->config['store_id'] 
            ?? $this->config['EPSStoreID'] 
            ?? ''
        );
    }

    /**
     * Get API Username supporting both standard and official EPS key names.
     */
    protected function getUsername(): string
    {
        return (string)(
            $this->config['username'] 
            ?? $this->config['EPSUserName'] 
            ?? ''
        );
    }

    /**
     * Get API Password supporting both standard and official EPS key names.
     */
    protected function getPassword(): string
    {
        return (string)(
            $this->config['password'] 
            ?? $this->config['EPSPassword'] 
            ?? ''
        );
    }

    /**
     * Get Hash Key supporting both standard and official EPS key names.
     */
    protected function getHashKey(): string
    {
        return (string)(
            $this->config['hash_key'] 
            ?? $this->config['EPSHashkey'] 
            ?? $this->config['hashkey'] 
            ?? ''
        );
    }

    /**
     * Get Device Type ID (default '1').
     */
    protected function getDeviceTypeId(): string
    {
        return (string)(
            $this->config['device_type_id'] 
            ?? $this->config['EPSDeviceTypeID'] 
            ?? '1'
        );
    }

    /**
     * Generate HMAC SHA-512 Base64-encoded signature required by EPS.
     */
    protected function generateHash(string $payload, string $hashKey): string
    {
        $data = hash_hmac('sha512', (string)$payload, (string)$hashKey, true);
        return base64_encode($data);
    }

    /**
     * Retrieve Bearer Auth Token from EPS.
     *
     * @throws PaymentException
     */
    protected function getToken(): string
    {
        $username = $this->getUsername();
        $password = $this->getPassword();
        $hashKey  = $this->getHashKey();

        if (empty($username) || empty($password) || empty($hashKey)) {
            throw new PaymentException("EPS Gateway error: Missing API credentials (username, password, or hash_key).");
        }

        $xHash = $this->generateHash($username, $hashKey);
        $url   = $this->getBaseUrl() . '/v1/Auth/GetToken';

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'x-hash'       => $xHash,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => [
                    'userName' => $username,
                    'password' => $password,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['token']) && !empty($responseData['token'])) {
                return $responseData['token'];
            }

            $errorMessage = $responseData['message'] 
                ?? $responseData['ErrorMessage'] 
                ?? $responseData['statusMessage'] 
                ?? 'Unable to retrieve EPS auth token';

            throw new PaymentException("EPS Auth Failed: " . $errorMessage);
        } catch (PaymentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError('EPS getToken Error: ' . $e->getMessage());
            throw new PaymentException("EPS Authentication Request Failed: " . $this->sanitizeLog($e->getMessage()));
        }
    }

    /**
     * Initiate payment session with EPS.
     */
    public function pay(array $data): array
    {
        try {
            $transactionId = $data['transaction_id'] ?? $this->generateUuid();
            $orderId = $data['order_id'] ?? $data['CustomerOrderId'] ?? ('ORD_' . time());
            $hashKey = $this->getHashKey();
            $merchantId = $this->getMerchantId();
            $storeId = $this->getStoreId();

            if (empty($merchantId) || empty($storeId)) {
                return $this->formatResponse(false, 'EPS Merchant ID or Store ID is not configured.', $transactionId);
            }

            // 1. Obtain Bearer Auth Token
            $token = $this->getToken();

            // 2. Generate x-hash using transactionId and Hash Key
            $xHash = $this->generateHash($transactionId, $hashKey);

            $successUrl = $this->resolveUrl($this->config['success_url'] ?? $this->config['callback_url'] ?? '/payment/eps/success');
            $failUrl    = $this->resolveUrl($this->config['fail_url'] ?? '/payment/eps/fail');
            $cancelUrl  = $this->resolveUrl($this->config['cancel_url'] ?? '/payment/eps/cancel');

            // Build product items array
            $productList = $data['product_list'] ?? $data['ProductList'] ?? [];
            if (empty($productList)) {
                $productList = [
                    [
                        'ProductName'     => $data['product_name'] ?? 'Order Item',
                        'NoOfItem'        => (string)($data['no_of_items'] ?? '1'),
                        'ProductProfile'  => (string)($data['product_profile'] ?? 'general'),
                        'ProductCategory' => (string)($data['product_category'] ?? 'General'),
                        'ProductPrice'    => (string)$data['amount'],
                    ]
                ];
            }

            $payload = [
                'merchantId'            => $merchantId,
                'storeId'               => $storeId,
                'merchantTransactionId' => $transactionId,
                'CustomerOrderId'       => $orderId,
                'transactionTypeId'     => 1,
                'financialEntityId'     => 0,
                'version'               => '1',
                'transactionDate'       => date('c'),
                'transitionStatusId'    => 0,
                'totalAmount'           => (float)$data['amount'],
                'ipAddress'             => $this->getClientIp(),
                'deviceTypeId'          => $this->getDeviceTypeId(),
                'successUrl'            => $successUrl,
                'failUrl'               => $failUrl,
                'cancelUrl'             => $cancelUrl,
                'customerName'          => $data['customer_name'] ?? 'Customer',
                'customerEmail'         => $data['customer_email'] ?? 'customer@example.com',
                'customerPhone'         => $data['customer_phone'] ?? '01700000000',
                'customerAddress'       => $data['customer_address'] ?? 'Dhaka, Bangladesh',
                'customerAddress2'      => $data['customer_address2'] ?? '',
                'customerCity'          => $data['customer_city'] ?? 'Dhaka',
                'customerState'         => $data['customer_state'] ?? 'Dhaka',
                'customerPostcode'      => $data['customer_postcode'] ?? '1000',
                'customerCountry'       => $data['customer_country'] ?? 'Bangladesh',
                'shipmentName'          => $data['shipment_name'] ?? ($data['customer_name'] ?? 'Customer'),
                'shipmentAddress'       => $data['shipment_address'] ?? ($data['customer_address'] ?? 'Dhaka, Bangladesh'),
                'shipmentAddress2'      => $data['shipment_address2'] ?? '',
                'shipmentCity'          => $data['shipment_city'] ?? 'Dhaka',
                'shipmentState'         => $data['shipment_state'] ?? 'Dhaka',
                'shipmentPostcode'      => $data['shipment_postcode'] ?? '1000',
                'shipmentCountry'       => $data['shipment_country'] ?? 'Bangladesh',
                'valueA'                => (string)($data['value_a'] ?? $data['user_id'] ?? ''),
                'valueB'                => (string)($data['value_b'] ?? ''),
                'valueC'                => (string)($data['value_c'] ?? ''),
                'valueD'                => (string)($data['value_d'] ?? ''),
                'shippingMethod'        => $data['shipping_method'] ?? 'Online Delivery',
                'noOfItem'              => (string)($data['no_of_items'] ?? count($productList)),
                'productName'           => $data['product_name'] ?? 'Payment',
                'productProfile'        => $data['product_profile'] ?? 'general',
                'productCategory'       => $data['product_category'] ?? 'General',
                'ProductList'           => $productList,
            ];

            if (isset($data['extra_payload']) && is_array($data['extra_payload'])) {
                $payload = array_merge($payload, $data['extra_payload']);
            }

            $url = $this->getBaseUrl() . '/v1/EPSEngine/InitializeEPS';

            $response = $this->client->post($url, [
                'headers' => [
                    'x-hash'        => $xHash,
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            $redirectUrl = $responseData['RedirectURL'] 
                ?? $responseData['redirectUrl'] 
                ?? $responseData['redirectURL'] 
                ?? $responseData['data']['RedirectURL'] 
                ?? null;

            if ($redirectUrl) {
                return $this->formatResponse(
                    true,
                    'EPS checkout URL generated successfully',
                    $transactionId,
                    $redirectUrl,
                    $responseData,
                    (float)$data['amount'],
                    $data['currency'] ?? 'BDT'
                );
            }

            $errorMessage = $responseData['ErrorMessage'] 
                ?? $responseData['errorMessage'] 
                ?? $responseData['message'] 
                ?? 'Failed to retrieve EPS payment URL';

            return $this->formatResponse(false, $errorMessage, $transactionId, null, $responseData);

        } catch (\Throwable $e) {
            $this->logError('EPS pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage(), $data['transaction_id'] ?? null);
        }
    }

    /**
     * Verify payment status using EPS API and callback payload.
     */
    public function verify(array $data): array
    {
        try {
            $transactionId = $data['MerchantTransactionId'] 
                ?? $data['merchantTransactionId'] 
                ?? $data['merchant_transaction_id'] 
                ?? $data['EPSTransactionId_'] 
                ?? $data['EPSTransactionId'] 
                ?? $data['transaction_id'] 
                ?? $data['tran_id'] 
                ?? null;

            if (!$transactionId) {
                return $this->formatResponse(false, 'Missing transaction identifier (MerchantTransactionId or transaction_id) for EPS verification.');
            }

            // Perform server-to-server inquiry with EPS
            $token = $this->getToken();
            $xHash = $this->generateHash($transactionId, $this->getHashKey());

            $url = $this->getBaseUrl() . '/v1/EPSEngine/CheckMerchantTransactionStatus?merchantTransactionId=' . urlencode($transactionId);

            $response = $this->client->get($url, [
                'headers' => [
                    'x-hash'        => $xHash,
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ]);

            $apiData = json_decode($response->getBody()->getContents(), true);

            $rawStatus = $apiData['transactionStatus'] 
                ?? $apiData['status'] 
                ?? $apiData['data']['transactionStatus'] 
                ?? $apiData['data']['status'] 
                ?? $data['Status'] 
                ?? $data['status'] 
                ?? '';

            $status = strtoupper((string)$rawStatus);

            $amount = null;
            if (isset($apiData['totalAmount'])) {
                $amount = (float)$apiData['totalAmount'];
            } elseif (isset($apiData['data']['totalAmount'])) {
                $amount = (float)$apiData['data']['totalAmount'];
            } elseif (isset($data['amount'])) {
                $amount = (float)$data['amount'];
            }

            if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'PAID'])) {
                return $this->formatResponse(
                    true,
                    'Payment verified successfully with EPS',
                    $transactionId,
                    null,
                    $apiData ?? $data,
                    $amount,
                    'BDT'
                );
            }

            $failMsg = !empty($status) ? "EPS payment status: {$status}" : 'EPS payment verification failed';

            return $this->formatResponse(false, $failMsg, $transactionId, null, $apiData ?? $data);

        } catch (\Throwable $e) {
            $this->logError('EPS verify() Error: ' . $e->getMessage());

            // Check if callback had a fallback status in query params
            $callbackStatus = strtoupper((string)($data['Status'] ?? $data['status'] ?? ''));
            $transactionId = $data['MerchantTransactionId'] ?? $data['merchantTransactionId'] ?? $data['transaction_id'] ?? null;

            if ($callbackStatus === 'SUCCESS' && $transactionId) {
                $this->logWarning("EPS verify() API call threw exception, but callback status indicated SUCCESS for TXN: {$transactionId}. Exception: " . $e->getMessage());
            }

            return $this->formatResponse(false, 'EPS Verification Error: ' . $e->getMessage(), $transactionId);
        }
    }

    /**
     * EPS does not have an automated refund API.
     */
    public function refund(string $transactionId): array
    {
        return $this->formatResponse(
            false,
            'EPS does not provide an automated online refund API. Please initiate refunds through the EPS merchant portal.',
            $transactionId
        );
    }

    /**
     * Handle EPS IPN / Webhook notification.
     */
    public function webhook(array $payload): array
    {
        $transactionId = $payload['MerchantTransactionId'] 
            ?? $payload['merchantTransactionId'] 
            ?? $payload['merchant_transaction_id'] 
            ?? $payload['EPSTransactionId_'] 
            ?? $payload['transaction_id'] 
            ?? null;

        if (!$transactionId) {
            return $this->formatResponse(false, 'Invalid webhook payload: Missing transaction identifier.');
        }

        return $this->verify($payload);
    }
}
