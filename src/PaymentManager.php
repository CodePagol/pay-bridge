<?php

namespace PayBridge\Payment;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use PayBridge\Payment\Drivers\SSLCommerzDriver;
use PayBridge\Payment\Drivers\StripeDriver;
use PayBridge\Payment\Drivers\PayPalDriver;
use PayBridge\Payment\Drivers\BkashTokenizeDriver;
use PayBridge\Payment\Drivers\BkashPwgDriver;
use PayBridge\Payment\Drivers\SurjoPayDriver;
use PayBridge\Payment\Drivers\AamarpayDriver;
use PayBridge\Payment\Drivers\NagadDriver;
use PayBridge\Payment\Drivers\SonaliPayDriver;
use PayBridge\Payment\Drivers\RocketDriver;
use PayBridge\Payment\Drivers\UpayDriver;
use PayBridge\Payment\Drivers\PortPayDriver;
use PayBridge\Payment\Drivers\EpsDriver;
use PayBridge\Payment\Drivers\BinancePayDriver;
use PayBridge\Payment\Drivers\NowPaymentsDriver;
use PayBridge\Payment\Drivers\BanglaQrDriver;

class PaymentManager extends Manager
{
    /**
     * Resolve configuration for a driver, prioritizing database settings if available.
     *
     * @param string $driver
     * @return array
     */
    protected function resolveConfig(string $driver): array
    {
        $fileConfig = $this->config->get("payment.drivers.{$driver}", []);
        if (!is_array($fileConfig)) {
            $fileConfig = [];
        }

        try {
            if (class_exists(\PayBridge\Payment\Models\PaymentGatewaySetting::class) && \Illuminate\Support\Facades\Schema::hasTable('payment_gateway_settings')) {
                $setting = \PayBridge\Payment\Models\PaymentGatewaySetting::where('code', $driver)->first();
                if ($setting) {
                    $dbCredentials = $setting->credentials ?? [];
                    return array_merge($fileConfig, $dbCredentials, [
                        'sandbox'   => $setting->is_sandbox,
                        'is_active' => $setting->is_active,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback to file configuration
        }

        return $fileConfig;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        try {
            if (class_exists(\PayBridge\Payment\Models\PaymentGatewaySetting::class) && \Illuminate\Support\Facades\Schema::hasTable('payment_gateway_settings')) {
                $default = \PayBridge\Payment\Models\PaymentGatewaySetting::where('is_default', true)->value('code');
                if ($default) {
                    return $default;
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return $this->config->get('payment.default', 'sslcommerz');
    }

    /**
     * Get list of active gateways enabled by admin for customer checkout.
     *
     * @return array
     */
    public function getActiveGateways(): array
    {
        try {
            if (class_exists(\PayBridge\Payment\Models\PaymentGatewaySetting::class) && \Illuminate\Support\Facades\Schema::hasTable('payment_gateway_settings')) {
                return \PayBridge\Payment\Models\PaymentGatewaySetting::active()->get()->toArray();
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return [];
    }

    /**
     * Create SSLCommerz driver instance.
     */
    public function createSslcommerzDriver()
    {
        return new SSLCommerzDriver($this->resolveConfig('sslcommerz'));
    }

    /**
     * Create Stripe driver instance.
     */
    public function createStripeDriver()
    {
        return new StripeDriver($this->resolveConfig('stripe'));
    }

    /**
     * Create PayPal driver instance.
     */
    public function createPaypalDriver()
    {
        return new PayPalDriver($this->resolveConfig('paypal'));
    }

    /**
     * Create Bkash Tokenize driver instance.
     */
    public function createBkashTokenizeDriver()
    {
        return new BkashTokenizeDriver($this->resolveConfig('bkash_tokenize'));
    }

    /**
     * Create Bkash PWG driver instance.
     */
    public function createBkashPwgDriver()
    {
        return new BkashPwgDriver($this->resolveConfig('bkash_pwg'));
    }

    /**
     * Create SurjoPay driver instance.
     */
    public function createSurjopayDriver()
    {
        return new SurjoPayDriver($this->resolveConfig('surjopay'));
    }

    /**
     * Create AamarPay driver instance.
     */
    public function createAamarpayDriver()
    {
        return new AamarpayDriver($this->resolveConfig('aamarpay'));
    }

    /**
     * Create Nagad driver instance.
     */
    public function createNagadDriver()
    {
        return new NagadDriver($this->resolveConfig('nagad'));
    }

    /**
     * Create Sonali Pay driver instance.
     */
    public function createSonalipayDriver()
    {
        return new SonaliPayDriver($this->resolveConfig('sonalipay'));
    }

    /**
     * Create Rocket driver instance.
     */
    public function createRocketDriver()
    {
        return new RocketDriver($this->resolveConfig('rocket'));
    }

    /**
     * Create Upay driver instance.
     */
    public function createUpayDriver()
    {
        return new UpayDriver($this->resolveConfig('upay'));
    }

    /**
     * Create PortPay driver instance.
     */
    public function createPortpayDriver()
    {
        return new PortPayDriver($this->resolveConfig('portpay'));
    }

    /**
     * Create EPS driver instance.
     */
    public function createEpsDriver()
    {
        return new EpsDriver($this->resolveConfig('eps'));
    }

    /**
     * Create Binance Pay driver instance.
     */
    public function createBinancePayDriver()
    {
        return new BinancePayDriver($this->resolveConfig('binance_pay'));
    }

    /**
     * Create NOWPayments driver instance.
     */
    public function createNowpaymentsDriver()
    {
        return new NowPaymentsDriver($this->resolveConfig('nowpayments'));
    }

    /**
     * Create Bangla QR driver instance.
     */
    public function createBanglaQrDriver()
    {
        return new BanglaQrDriver($this->resolveConfig('bangla_qr'));
    }

    /**
     * Build a driver instance with dynamic runtime configuration.
     * Useful for multi-merchant / SaaS / dynamic database credentials.
     *
     * @param string $driver
     * @param array $config
     * @return \PayBridge\Payment\Contracts\PaymentGatewayInterface
     */
    public function build(string $driver, array $config = []): \PayBridge\Payment\Contracts\PaymentGatewayInterface
    {
        $driverClassMap = [
            'sslcommerz'     => SSLCommerzDriver::class,
            'stripe'         => StripeDriver::class,
            'paypal'         => PayPalDriver::class,
            'bkash_tokenize' => BkashTokenizeDriver::class,
            'bkash_pwg'      => BkashPwgDriver::class,
            'surjopay'       => SurjoPayDriver::class,
            'aamarpay'       => AamarpayDriver::class,
            'nagad'          => NagadDriver::class,
            'sonalipay'      => SonaliPayDriver::class,
            'rocket'         => RocketDriver::class,
            'upay'           => UpayDriver::class,
            'portpay'        => PortPayDriver::class,
            'eps'            => EpsDriver::class,
            'binance_pay'    => BinancePayDriver::class,
            'binancepay'     => BinancePayDriver::class,
            'nowpayments'    => NowPaymentsDriver::class,
            'bangla_qr'      => BanglaQrDriver::class,
            'banglaqr'       => BanglaQrDriver::class,
        ];

        $normalized = strtolower(str_replace('-', '_', $driver));
        $class = $driverClassMap[$normalized] ?? $this->config->get("payment.drivers.{$driver}.class");

        if ($class && class_exists($class)) {
            $baseConfig = $this->config->get("payment.drivers.{$driver}", []);
            $mergedConfig = array_merge(is_array($baseConfig) ? $baseConfig : [], $config);
            return new $class($mergedConfig);
        }

        throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
    }

    /**
     * Get a list of all supported gateways with human-readable titles and categories.
     * Useful for rendering payment options on checkout pages.
     *
     * @return array
     */
    public function getSupportedGateways(): array
    {
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
     * Get list of all supported gateway codes.
     *
     * @return array
     */
    public function getSupportedDrivers(): array
    {
        return array_keys($this->getSupportedGateways());
    }

    /**
     * Dynamically resolve custom driver.
     *
     * @param string $driver
     * @return mixed
     */
    protected function createDriver($driver)
    {
        // Intercept for dynamically registered or non-standard drivers
        try {
            return parent::createDriver($driver);
        } catch (InvalidArgumentException $e) {
            $customDriverClass = $this->config->get("payment.drivers.{$driver}.class");
            if ($customDriverClass && class_exists($customDriverClass)) {
                return new $customDriverClass($this->config->get("payment.drivers.{$driver}"));
            }
            throw $e;
        }
    }
}
