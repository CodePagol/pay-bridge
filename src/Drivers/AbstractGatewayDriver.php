<?php

namespace PayBridge\Payment\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PayBridge\Payment\Contracts\PaymentGatewayInterface;
use PayBridge\Payment\Exceptions\PaymentException;
use PayBridge\Payment\Events\PaymentSuccess;
use PayBridge\Payment\Events\PaymentFailed;

abstract class AbstractGatewayDriver implements PaymentGatewayInterface
{
    protected array $config;
    protected Client $client;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'timeout'  => 30.0,
            // Strictly enable SSL verification by default for financial security against MITM attacks.
            'verify'   => $this->config['ssl_verify'] ?? true,
        ]);
    }

    /**
     * Determine if we are running in sandbox environment.
     *
     * @return bool
     */
    protected function isSandbox(): bool
    {
        return isset($this->config['sandbox']) && $this->config['sandbox'] === true;
    }

    /**
     * Format standard response array.
     *
     * @param bool $success
     * @param string $message
     * @param string|null $transactionId
     * @param string|null $redirectUrl
     * @param array $rawResponse
     * @param float|null $amount
     * @param string|null $currency
     * @return array
     */
    protected function formatResponse(
        bool $success,
        string $message,
        ?string $transactionId = null,
        ?string $redirectUrl = null,
        array $rawResponse = [],
        ?float $amount = null,
        ?string $currency = null
    ): array {
        // Fire events safely without crashing in environments where Event classes or listeners aren't loaded.
        if (function_exists('event')) {
            try {
                if ($success && $transactionId) {
                    event(new PaymentSuccess($transactionId, $rawResponse));
                } elseif (!$success && $transactionId) {
                    event(new PaymentFailed($transactionId, $message, $rawResponse));
                }
            } catch (\Throwable $e) {
                // Gracefully ignore event dispatch failure
            }
        }

        return [
            'success'        => $success,
            'message'        => $message,
            'transaction_id' => $transactionId,
            'redirect_url'   => $redirectUrl,
            'amount'         => $amount,
            'currency'       => $currency,
            'raw_response'   => $rawResponse,
        ];
    }

    /**
     * Sanitize error message to prevent leaking secrets into logs.
     */
    protected function sanitizeLog(string $message): string
    {
        return preg_replace('/(password|secret|key|token|auth)=([^& \s]+)/i', '$1=***REDACTED***', $message) ?? $message;
    }

    /**
     * Safe logger compatible with Laravel, Symfony, WordPress, and Raw PHP.
     */
     protected function log(string $level, string $message): void
     {
         if (class_exists(\Illuminate\Support\Facades\Log::class) && function_exists('app')) {
             try {
                 \Illuminate\Support\Facades\Log::log($level, $message);
                 return;
             } catch (\Throwable $e) {
                 // Fallback to error_log
             }
         }

         @error_log("[PayBridge " . strtoupper($level) . "] " . $message);
     }

     protected function logError(string $message): void
     {
         $this->log('error', $message);
     }

     protected function logWarning(string $message): void
     {
         $this->log('warning', $message);
     }

     protected function logCritical(string $message): void
     {
         $this->log('critical', $message);
     }

     /**
      * Fire PaymentSuccess event if event dispatcher is available.
      */
     protected function fireSuccessEvent(?string $transactionId, array $payload = []): void
     {
         if ($transactionId && function_exists('event')) {
             try {
                 event(new PaymentSuccess($transactionId, $payload));
             } catch (\Throwable $e) {
                 // Gracefully ignore if events are not supported in current environment
             }
         }
     }

     /**
      * Fire PaymentFailed event if event dispatcher is available.
      */
     protected function fireFailedEvent(?string $transactionId, array $payload = []): void
     {
         if ($transactionId && function_exists('event')) {
             try {
                 event(new PaymentFailed($transactionId, 'Payment failed', $payload));
             } catch (\Throwable $e) {
                 // Gracefully ignore if events are not supported in current environment
             }
         }
     }

     /**
      * Safely resolve client IP across Laravel, frameworks, and Raw PHP.
      */
     protected function getClientIp(): string
     {
         if (function_exists('request') && request()) {
             try {
                 $ip = request()->ip();
                 if (!empty($ip)) {
                     return $ip;
                 }
             } catch (\Throwable $e) {
                 // Fallback
             }
         }

         return $_SERVER['HTTP_CF_CONNECTING_IP']
             ?? $_SERVER['HTTP_X_FORWARDED_FOR']
             ?? $_SERVER['REMOTE_ADDR']
             ?? '127.0.0.1';
     }

     /**
      * Safely get HTTP header across Laravel and Raw PHP.
      */
     protected function getHeader(string $name): ?string
     {
         if (function_exists('request') && request()) {
             try {
                 $val = request()->header($name);
                 if ($val !== null) {
                     return $val;
                 }
             } catch (\Throwable $e) {
                 // Fallback
             }
         }

         $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
         return $_SERVER[$serverKey] ?? null;
     }

     /**
      * Safely get raw request body across Laravel and Raw PHP.
      */
     protected function getRawBody(): string
     {
         if (function_exists('request') && request()) {
             try {
                 $body = request()->getContent();
                 if (!empty($body)) {
                     return $body;
                 }
             } catch (\Throwable $e) {
                 // Fallback
             }
         }

         return file_get_contents('php://input') ?: '';
     }

    /**
     * Send HTTP POST Request
     */
    protected function postRequest(string $url, array $params, array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'form_params' => $params
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            $this->logError("Gateway Request Error: " . $this->sanitizeLog($e->getMessage()));
            throw new PaymentException("Communication with payment gateway failed: " . $this->sanitizeLog($e->getMessage()));
        }
    }
    
    /**
     * Send HTTP POST JSON Request
     */
    protected function postJsonRequest(string $url, array $json, array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'json' => $json
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            $this->logError("Gateway JSON Request Error: " . $this->sanitizeLog($e->getMessage()));
            throw new PaymentException("Communication with payment gateway failed: " . $this->sanitizeLog($e->getMessage()));
        }
    }

    /**
     * Generate a cryptographically secure UUID v4 (RFC 4122 compliant)
     * without requiring any external dependencies.
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Safely resolve and format a URL across Laravel, Symfony, WordPress, and Raw PHP.
     *
     * @param string|null $url Relative path or absolute URL
     * @return string
     */
    public function resolveUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // If already an absolute URL (http:// or https://)
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // If running in Laravel with url() helper available
        if (function_exists('url')) {
            try {
                return url($url);
            } catch (\Throwable $e) {
                // Fallback to manual resolution
            }
        }

        // Fallback for Raw PHP and non-Laravel environments
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $leadingSlash = str_starts_with($url, '/') ? '' : '/';
        return "{$scheme}://{$host}{$leadingSlash}{$url}";
    }
}
