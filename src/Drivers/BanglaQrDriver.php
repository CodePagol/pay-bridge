<?php

namespace PayBridge\Payment\Drivers;

use PayBridge\Payment\Exceptions\PaymentException;

class BanglaQrDriver extends AbstractGatewayDriver
{
    /**
     * Format a standard EMVCo Tag-Length-Value (TLV) element.
     */
    protected function formatTlv(string $tag, string $value): string
    {
        $length = strlen($value);
        return sprintf('%s%02d%s', $tag, $length, $value);
    }

    /**
     * Calculate EMVCo CRC-16 CCITT checksum (Polynomial: 0x1021, Initial: 0xFFFF).
     */
    public function calculateCrc16(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(sprintf('%04X', $crc));
    }

    /**
     * Generate Bangladesh Bank Interoperable Bangla QR EMVCo Payload.
     */
    public function generateEmvCoPayload(array $data): string
    {
        $acquirerBin  = $this->config['acquirer_bin'] ?? '000001';
        $merchantId   = $this->config['merchant_id'] ?? 'MERCHANT001';
        $terminalId   = $this->config['terminal_id'] ?? 'TID001';
        $merchantName = substr($this->config['merchant_name'] ?? 'PayBridge Merchant', 0, 25);
        $merchantCity = substr($this->config['merchant_city'] ?? 'Dhaka', 0, 15);
        $mcc          = $this->config['mcc'] ?? '5411'; // Default retail MCC

        $amount       = number_format((float)($data['amount'] ?? 0), 2, '.', '');
        $tranId       = $data['transaction_id'] ?? $this->generateUuid();

        // 00: Payload Format Indicator (01)
        $payload = $this->formatTlv('00', '01');

        // 01: Point of Initiation Method (12 = Dynamic QR with specific amount, 11 = Static)
        $payload .= $this->formatTlv('01', '12');

        // 26: Merchant Account Information (Bangladesh Bank Bangla QR Template)
        $subtag00 = $this->formatTlv('00', 'COM.BANGLADESH.BANGLAQR');
        $subtag01 = $this->formatTlv('01', $acquirerBin);
        $subtag02 = $this->formatTlv('02', $merchantId);
        $subtag03 = $this->formatTlv('03', $terminalId);
        $merchantAccountInfo = $subtag00 . $subtag01 . $subtag02 . $subtag03;
        $payload .= $this->formatTlv('26', $merchantAccountInfo);

        // 52: Merchant Category Code (MCC)
        $payload .= $this->formatTlv('52', $mcc);

        // 53: Transaction Currency (050 = BDT ISO 4217 numeric code)
        $payload .= $this->formatTlv('53', '050');

        // 54: Transaction Amount
        $payload .= $this->formatTlv('54', $amount);

        // 58: Country Code (BD = Bangladesh)
        $payload .= $this->formatTlv('58', 'BD');

        // 59: Merchant Name
        $payload .= $this->formatTlv('59', $merchantName);

        // 60: Merchant City
        $payload .= $this->formatTlv('60', $merchantCity);

        // 62: Additional Data Field Template (Bill number / Transaction ID)
        $add01 = $this->formatTlv('01', substr($tranId, 0, 25));
        $add07 = $this->formatTlv('07', substr($terminalId, 0, 10));
        $additionalData = $add01 . $add07;
        $payload .= $this->formatTlv('62', $additionalData);

        // 63: CRC16 Checksum placeholder
        $payloadWithCrcTag = $payload . '6304';
        $checksum = $this->calculateCrc16($payloadWithCrcTag);

        return $payloadWithCrcTag . $checksum;
    }

    /**
     * Initiate Bangla QR payment.
     */
    public function pay(array $data): array
    {
        try {
            $tranId = $data['transaction_id'] ?? $this->generateUuid();
            $data['transaction_id'] = $tranId;
            $amount = (float)($data['amount'] ?? 0);

            // If external Acquirer API URL is configured (e.g. City Bank / SSLCommerz / PortPay QR API)
            $apiUrl = $this->config['api_url'] ?? null;
            $rawResponse = [];
            $emvcoPayload = null;
            $qrImageUrl = null;
            $checkoutUrl = null;

            if (!empty($apiUrl)) {
                $postData = [
                    'merchant_id'    => $this->config['merchant_id'] ?? '',
                    'terminal_id'    => $this->config['terminal_id'] ?? '',
                    'transaction_id' => $tranId,
                    'amount'         => $amount,
                    'currency'       => 'BDT',
                    'merchant_name'  => $this->config['merchant_name'] ?? 'Merchant',
                    'callback_url'   => $this->resolveUrl($this->config['callback_url'] ?? '/payment/banglaqr/callback'),
                ];

                $response = $this->postJsonRequest($apiUrl, $postData);
                $rawResponse = $response;

                $emvcoPayload = $response['qr_string'] ?? ($response['qr_payload'] ?? null);
                $qrImageUrl   = $response['qr_image_url'] ?? ($response['qr_url'] ?? null);
                $checkoutUrl  = $response['redirect_url'] ?? ($response['checkout_url'] ?? null);
            }

            // If no acquirer API or payload not returned, generate standard Bangladesh Bank EMVCo string
            if (empty($emvcoPayload)) {
                $emvcoPayload = $this->generateEmvCoPayload($data);
            }

            // Generate clean QR code image URL if not returned from external API
            if (empty($qrImageUrl)) {
                $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($emvcoPayload);
            }

            $formatted = $this->formatResponse(
                true,
                'Bangla QR generated successfully',
                $tranId,
                $checkoutUrl ?? $qrImageUrl,
                array_merge($rawResponse, ['qr_string' => $emvcoPayload, 'qr_image' => $qrImageUrl]),
                $amount,
                'BDT'
            );

            // Append specific QR fields
            $formatted['qr_payload']   = $emvcoPayload;
            $formatted['qr_image_url'] = $qrImageUrl;

            return $formatted;

        } catch (\Exception $e) {
            $this->logError('Bangla QR pay() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Verify payment status for a Bangla QR transaction.
     */
    public function verify(array $data): array
    {
        try {
            $transactionId = $data['transaction_id'] ?? ($data['tran_id'] ?? null);

            if (!$transactionId) {
                return $this->formatResponse(false, 'Transaction ID is required for Bangla QR verification.');
            }

            // If an acquirer verification API endpoint is provided
            $verifyUrl = $this->config['verify_url'] ?? null;
            if (!empty($verifyUrl)) {
                $response = $this->postJsonRequest($verifyUrl, [
                    'merchant_id'    => $this->config['merchant_id'] ?? '',
                    'transaction_id' => $transactionId,
                ]);

                $status = strtoupper($response['status'] ?? ($response['payment_status'] ?? ''));
                if (in_array($status, ['SUCCESS', 'PAID', 'COMPLETED'])) {
                    $amount = isset($response['amount']) ? (float)$response['amount'] : null;
                    return $this->formatResponse(true, 'Payment verified successfully via Bangla QR', $transactionId, null, $response, $amount, 'BDT');
                }

                return $this->formatResponse(false, 'Bangla QR payment not completed or pending', $transactionId, null, $response);
            }

            // If verifying via incoming callback payload (e.g. from bank IPN)
            if (isset($data['status']) && in_array(strtoupper($data['status']), ['SUCCESS', 'PAID', 'VALID'])) {
                $amount = isset($data['amount']) ? (float)$data['amount'] : null;
                return $this->formatResponse(true, 'Payment verified', $transactionId, null, $data, $amount, 'BDT');
            }

            return $this->formatResponse(false, 'Unable to verify Bangla QR transaction', $transactionId, null, $data);

        } catch (\Exception $e) {
            $this->logError('Bangla QR verify() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }

    /**
     * Refund a processed Bangla QR payment.
     */
    public function refund(string $transactionId): array
    {
        return $this->formatResponse(
            false,
            'Bangla QR refunds must be processed through the acquirer bank merchant portal.',
            $transactionId
        );
    }

    /**
     * Handle incoming IPN / Webhook from Bangla QR Acquirer Bank.
     */
    public function webhook(array $payload): array
    {
        try {
            // Optional IPN Secret validation if configured
            $secret = $this->config['secret_key'] ?? null;
            if (!empty($secret)) {
                $receivedSig = $this->getHeader('X-BanglaQR-Signature') ?? $this->getHeader('x-banglaqr-signature');
                if ($receivedSig) {
                    $sorted = $payload;
                    ksort($sorted);
                    $calculatedSig = hash_hmac('sha256', json_encode($sorted, JSON_UNESCAPED_SLASHES), $secret);
                    if (!hash_equals($calculatedSig, $receivedSig)) {
                        return $this->formatResponse(false, 'Invalid Bangla QR webhook signature', null, null, $payload);
                    }
                }
            }

            $transactionId = $payload['transaction_id'] ?? ($payload['tran_id'] ?? ($payload['bill_number'] ?? null));
            $status = strtoupper($payload['status'] ?? ($payload['payment_status'] ?? ''));
            $amount = isset($payload['amount']) ? (float)$payload['amount'] : null;

            if (in_array($status, ['SUCCESS', 'PAID', 'COMPLETED', '0000'])) {
                return $this->formatResponse(true, 'Bangla QR Webhook: Payment confirmed', $transactionId, null, $payload, $amount, 'BDT');
            }

            return $this->formatResponse(false, "Bangla QR Webhook status: {$status}", $transactionId, null, $payload);

        } catch (\Exception $e) {
            $this->logError('Bangla QR webhook() Error: ' . $e->getMessage());
            return $this->formatResponse(false, $e->getMessage());
        }
    }
}
