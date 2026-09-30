<?php

namespace PayBridge\Payment;

use PayBridge\Payment\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

/**
 * Universal PayBridge Client for Raw PHP, WordPress, CodeIgniter, Symfony, and Laravel.
 */
class PayBridge
{
    /**
     * Driver mapping for direct standalone instantiation.
     */
    protected static array $drivers = [
        'sslcommerz'     => Drivers\SSLCommerzDriver::class,
        'ssl_commerz'    => Drivers\SSLCommerzDriver::class,
        'stripe'         => Drivers\StripeDriver::class,
        'paypal'         => Drivers\PayPalDriver::class,
        'bkash_tokenize' => Drivers\BkashTokenizeDriver::class,
        'bkashtokenize'  => Drivers\BkashTokenizeDriver::class,
        'bkash_pwg'      => Drivers\BkashPwgDriver::class,
        'bkashpwg'       => Drivers\BkashPwgDriver::class,
        'surjopay'       => Drivers\SurjoPayDriver::class,
        'surjo_pay'      => Drivers\SurjoPayDriver::class,
        'aamarpay'       => Drivers\AamarpayDriver::class,
        'aamar_pay'      => Drivers\AamarpayDriver::class,
        'nagad'          => Drivers\NagadDriver::class,
        'sonalipay'      => Drivers\SonaliPayDriver::class,
        'sonali_pay'     => Drivers\SonaliPayDriver::class,
        'rocket'         => Drivers\RocketDriver::class,
        'upay'           => Drivers\UpayDriver::class,
        'portpay'        => Drivers\PortPayDriver::class,
        'port_pay'       => Drivers\PortPayDriver::class,
        'eps'            => Drivers\EpsDriver::class,
        'binance_pay'    => Drivers\BinancePayDriver::class,
        'binancepay'     => Drivers\BinancePayDriver::class,
        'nowpayments'    => Drivers\NowPaymentsDriver::class,
        'now_payments'   => Drivers\NowPaymentsDriver::class,
        'bangla_qr'      => Drivers\BanglaQrDriver::class,
        'banglaqr'       => Drivers\BanglaQrDriver::class,
    ];

    /**
     * Create a payment gateway driver instance for Raw PHP or any framework.
     *
     * @param string $driver Gateway name (e.g. 'bkash_tokenize', 'sslcommerz', 'stripe', 'binance_pay')
     * @param array $config Gateway credentials and settings (optional if running in Laravel)
     * @return PaymentGatewayInterface
     *
     * @throws InvalidArgumentException
     */
    public static function make(string $driver, array $config = []): PaymentGatewayInterface
    {
        // If in Laravel, resolve through PaymentManager
        if (function_exists('app') && app()->bound(PaymentManager::class)) {
            return empty($config)
                ? app(PaymentManager::class)->driver($driver)
                : app(PaymentManager::class)->build($driver, $config);
        }

        $normalized = strtolower(str_replace('-', '_', $driver));

        if (!isset(static::$drivers[$normalized])) {
            throw new InvalidArgumentException("Unsupported PayBridge gateway driver: [{$driver}].");
        }

        $class = static::$drivers[$normalized];
        return new $class($config);
    }

    /**
     * Alias of make(). Supports resolving default driver when in Laravel.
     */
    public static function driver(?string $driver = null, array $config = []): PaymentGatewayInterface
    {
        if ($driver === null && function_exists('app') && app()->bound(PaymentManager::class)) {
            return app(PaymentManager::class)->driver();
        }

        return static::make($driver ?? 'sslcommerz', $config);
    }

    /**
     * Get list of all supported gateway codes.
     *
     * @return array
     */
    public static function getSupportedDrivers(): array
    {
        if (function_exists('app') && app()->bound(PaymentManager::class)) {
            return app(PaymentManager::class)->getSupportedDrivers();
        }

        return array_keys(static::getSupportedGateways());
    }

    /**
     * Get a list of all supported gateways with human-readable titles and categories.
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        if (function_exists('app') && app()->bound(PaymentManager::class)) {
            return app(PaymentManager::class)->getSupportedGateways();
        }

        return [
            'sslcommerz'     => ['name' => 'SSLCommerz', 'category' => 'aggregator', 'description' => 'Cards, Mobile Banking & Net Banking'],
            'bkash_tokenize' => ['name' => 'bKash Tokenized', 'category' => 'mfs', 'description' => 'bKash 1-click & agreement payment'],
            'bkash_pwg'      => ['name' => 'bKash PWG', 'category' => 'mfs', 'description' => 'bKash checkout URL'],
            'nagad'          => ['name' => 'Nagad', 'category' => 'mfs', 'description' => 'Nagad digital payment'],
            'rocket'         => ['name' => 'Rocket', 'category' => 'mfs', 'description' => 'DBBL Rocket payment'],
            'upay'           => ['name' => 'Upay', 'category' => 'mfs', 'description' => 'UCB Upay payment'],
            'aamarpay'       => ['name' => 'AamarPay', 'category' => 'aggregator', 'description' => 'Credit/Debit cards & MFS'],
            'surjopay'       => ['name' => 'SurjoPay', 'category' => 'aggregator', 'description' => 'Local card & MFS gateway'],
            'sonalipay'      => ['name' => 'Sonali Pay', 'category' => 'bank', 'description' => 'Sonali Bank payment gateway'],
            'portpay'        => ['name' => 'PortPay', 'category' => 'aggregator', 'description' => 'PortWallet gateway'],
            'eps'            => ['name' => 'EPS', 'category' => 'bank', 'description' => 'Easy Payment System'],
            'stripe'         => ['name' => 'Stripe', 'category' => 'card', 'description' => 'International Credit/Debit Cards'],
            'paypal'         => ['name' => 'PayPal', 'category' => 'wallet', 'description' => 'PayPal account & cards'],
            'binance_pay'    => ['name' => 'Binance Pay', 'category' => 'crypto', 'description' => 'Crypto payment with Binance app (USDT, BTC)'],
            'nowpayments'    => ['name' => 'NOWPayments', 'category' => 'crypto', 'description' => 'Accept 100+ Cryptocurrencies'],
            'bangla_qr'      => ['name' => 'Bangla QR', 'category' => 'qr', 'description' => 'National Interoperable QR (bKash, Nagad, Rocket & all Bank Apps)'],
        ];
    }

    /**
     * Get list of active gateways enabled by admin for customer checkout.
     *
     * @return array
     */
    public static function getActiveGateways(): array
    {
        if (function_exists('app') && app()->bound(PaymentManager::class)) {
            return app(PaymentManager::class)->getActiveGateways();
        }

        return static::getSupportedGateways();
    }

    /**
     * Safely resolve absolute URL for Raw PHP and Laravel environments.
     */
    public static function resolveUrl(?string $path = null): string
    {
        if (empty($path)) {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        if (function_exists('url')) {
            try {
                return (string) url($path);
            } catch (\Throwable $e) {
                // Fallback to manual resolution
            }
        }
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $leadingSlash = str_starts_with($path, '/') ? '' : '/';
        return "{$scheme}://{$host}{$leadingSlash}{$path}";
    }

    /**
     * Dynamically delegate static calls to Laravel's PaymentManager if available.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic($method, $parameters)
    {
        if (function_exists('app') && app()->bound(PaymentManager::class)) {
            return app(PaymentManager::class)->$method(...$parameters);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on PayBridge.");
    }
}
