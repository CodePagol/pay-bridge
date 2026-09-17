<?php

namespace PayBridge\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Exception;

class PaymentGatewaySetting extends Model
{
    protected $table = 'payment_gateway_settings';

    protected $fillable = [
        'code',
        'name',
        'category',
        'is_active',
        'is_sandbox',
        'is_default',
        'credentials',
        'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_sandbox'  => 'boolean',
        'is_default'  => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * Get decrypted credentials as an array.
     */
    public function getCredentialsAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }

        // Try decrypting first
        try {
            $decrypted = Crypt::decryptString($value);
            return json_decode($decrypted, true) ?? [];
        } catch (Exception $e) {
            // Fallback to plain JSON (in case encryption key changed or saved raw)
            return json_decode($value, true) ?? [];
        }
    }

    /**
     * Set credentials securely with encryption.
     */
    public function setCredentialsAttribute($value): void
    {
        $json = is_array($value) ? json_encode($value) : (string)$value;

        try {
            $this->attributes['credentials'] = Crypt::encryptString($json);
        } catch (Exception $e) {
            $this->attributes['credentials'] = $json;
        }
    }

    /**
     * Scope a query to only include active gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    /**
     * Set this gateway as the primary default gateway (unsetting any other default).
     */
    public function setAsDefault(): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * Remove this gateway from being default.
     */
    public function removeDefault(): void
    {
        $this->update(['is_default' => false]);
    }

    /**
     * Field definitions for all gateways so the Admin UI renders exact inputs.
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'bkash_tokenize' => [
                'name' => 'bKash (Tokenized)',
                'category' => 'MFS',
                'description' => 'Direct bKash wallet payment with tokenization',
                'fields' => [
                    'app_key'      => ['label' => 'App Key', 'type' => 'text', 'placeholder' => 'e.g. 5c98a3b2b00... or your bKash App Key'],
                    'app_secret'   => ['label' => 'App Secret', 'type' => 'password', 'placeholder' => 'Enter bKash App Secret'],
                    'username'     => ['label' => 'Username', 'type' => 'text', 'placeholder' => 'e.g. sandboxTokenizedUser02 or merchant_user'],
                    'password'     => ['label' => 'Password', 'type' => 'password', 'placeholder' => 'Enter bKash Merchant Password'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/bkash_tokenize/callback', 'placeholder' => '/payment/bkash_tokenize/callback'],
                ]
            ],
            'bkash_pwg' => [
                'name' => 'bKash (PWG / Checkout URL)',
                'category' => 'MFS',
                'description' => 'bKash Payment Web Gateway URL checkout',
                'fields' => [
                    'app_key'      => ['label' => 'App Key', 'type' => 'text', 'placeholder' => 'e.g. 5c98a3b2b00... or your bKash App Key'],
                    'app_secret'   => ['label' => 'App Secret', 'type' => 'password', 'placeholder' => 'Enter bKash App Secret'],
                    'username'     => ['label' => 'Username', 'type' => 'text', 'placeholder' => 'e.g. sandboxUser01 or merchant_user'],
                    'password'     => ['label' => 'Password', 'type' => 'password', 'placeholder' => 'Enter bKash Merchant Password'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/bkash_pwg/callback', 'placeholder' => '/payment/bkash_pwg/callback'],
                ]
            ],
            'nagad' => [
                'name' => 'Nagad',
                'category' => 'MFS',
                'description' => 'Nagad digital payment with RSA signature',
                'fields' => [
                    'merchant_id'     => ['label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'e.g. 683002007104225'],
                    'merchant_number' => ['label' => 'Merchant Account Number', 'type' => 'text', 'placeholder' => 'e.g. 017xxxxxxxx'],
                    'public_key'      => ['label' => 'Nagad Public Key (Base64)', 'type' => 'textarea', 'placeholder' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...\n-----END PUBLIC KEY-----"],
                    'private_key'     => ['label' => 'Your Private Key (Base64)', 'type' => 'textarea', 'placeholder' => "-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA0wZ8h2...\n-----END RSA PRIVATE KEY-----"],
                    'callback_url'    => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/nagad/callback', 'placeholder' => '/payment/nagad/callback'],
                ]
            ],
            'rocket' => [
                'name' => 'Rocket',
                'category' => 'MFS',
                'description' => 'Dutch-Bangla Bank Rocket Payment Gateway',
                'fields' => [
                    'merchant_id'  => ['label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'e.g. 1000000001'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/rocket/callback', 'placeholder' => '/payment/rocket/callback'],
                ]
            ],
            'upay' => [
                'name' => 'Upay',
                'category' => 'MFS',
                'description' => 'UCB Upay digital payment',
                'fields' => [
                    'merchant_id'  => ['label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'e.g. 88019xxxxxxxx'],
                    'merchant_key' => ['label' => 'Merchant Key / Secret', 'type' => 'password', 'placeholder' => 'Enter Upay Merchant Secret Key'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/upay/callback', 'placeholder' => '/payment/upay/callback'],
                ]
            ],
            'sslcommerz' => [
                'name' => 'SSLCommerz',
                'category' => 'Aggregators',
                'description' => 'Leading Bangladesh gateway supporting Cards, MFS & Internet Banking',
                'fields' => [
                    'store_id'       => ['label' => 'Store ID', 'type' => 'text', 'placeholder' => 'e.g. testbox or your Live Store ID'],
                    'store_password' => ['label' => 'Store Password', 'type' => 'password', 'placeholder' => 'Enter SSLCommerz Store Password'],
                    'success_url'    => ['label' => 'Success URL', 'type' => 'text', 'default' => '/payment/sslcommerz/success', 'placeholder' => '/payment/sslcommerz/success'],
                    'fail_url'       => ['label' => 'Fail URL', 'type' => 'text', 'default' => '/payment/sslcommerz/fail', 'placeholder' => '/payment/sslcommerz/fail'],
                    'cancel_url'     => ['label' => 'Cancel URL', 'type' => 'text', 'default' => '/payment/sslcommerz/cancel', 'placeholder' => '/payment/sslcommerz/cancel'],
                ]
            ],
            'aamarpay' => [
                'name' => 'AamarPay',
                'category' => 'Aggregators',
                'description' => 'Seamless card and mobile wallet checkout',
                'fields' => [
                    'store_id'      => ['label' => 'Store ID', 'type' => 'text', 'placeholder' => 'e.g. aamarpaytest or your Store ID'],
                    'signature_key' => ['label' => 'Signature Key', 'type' => 'password', 'placeholder' => 'e.g. 28c78bb1f45112f5e4e7fde07e58b08bec73f781'],
                    'success_url'   => ['label' => 'Success URL', 'type' => 'text', 'default' => '/payment/aamarpay/success', 'placeholder' => '/payment/aamarpay/success'],
                    'fail_url'      => ['label' => 'Fail URL', 'type' => 'text', 'default' => '/payment/aamarpay/fail', 'placeholder' => '/payment/aamarpay/fail'],
                    'cancel_url'    => ['label' => 'Cancel URL', 'type' => 'text', 'default' => '/payment/aamarpay/cancel', 'placeholder' => '/payment/aamarpay/cancel'],
                ]
            ],
            'surjopay' => [
                'name' => 'SurjoPay',
                'category' => 'Aggregators',
                'description' => 'Local card and digital wallet aggregator',
                'fields' => [
                    'merchant_name'     => ['label' => 'Merchant Name', 'type' => 'text', 'placeholder' => 'e.g. sp_merchant_test'],
                    'merchant_password' => ['label' => 'Merchant Password', 'type' => 'password', 'placeholder' => 'Enter SurjoPay Password'],
                    'merchant_prefix'   => ['label' => 'Merchant Prefix', 'type' => 'text', 'placeholder' => 'e.g. TXN or ISP'],
                    'success_url'       => ['label' => 'Success URL', 'type' => 'text', 'default' => '/payment/surjopay/success', 'placeholder' => '/payment/surjopay/success'],
                    'cancel_url'        => ['label' => 'Cancel URL', 'type' => 'text', 'default' => '/payment/surjopay/cancel', 'placeholder' => '/payment/surjopay/cancel'],
                ]
            ],
            'sonalipay' => [
                'name' => 'Sonali Pay',
                'category' => 'Banks',
                'description' => 'Sonali Bank Payment Gateway',
                'fields' => [
                    'merchant_id'  => ['label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'e.g. 100001'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/sonalipay/callback', 'placeholder' => '/payment/sonalipay/callback'],
                ]
            ],
            'portpay' => [
                'name' => 'PortPay',
                'category' => 'Aggregators',
                'description' => 'PortWallet payment solution',
                'fields' => [
                    'app_key'      => ['label' => 'App Key', 'type' => 'text', 'placeholder' => 'e.g. port_app_key_...'],
                    'secret_key'   => ['label' => 'Secret Key', 'type' => 'password', 'placeholder' => 'Enter PortPay Secret Key'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/portpay/callback', 'placeholder' => '/payment/portpay/callback'],
                ]
            ],
            'eps' => [
                'name' => 'Easy Payment System (EPS)',
                'category' => 'Banks',
                'description' => 'Direct bank transfer and internet banking',
                'fields' => [
                    'merchant_id'  => ['label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'e.g. EPS100001'],
                    'callback_url' => ['label' => 'Callback URL', 'type' => 'text', 'default' => '/payment/eps/callback', 'placeholder' => '/payment/eps/callback'],
                ]
            ],
            'stripe' => [
                'name' => 'Stripe',
                'category' => 'International Cards',
                'description' => 'Accept worldwide Visa, MasterCard, Amex cards',
                'fields' => [
                    'public_key'     => ['label' => 'Publishable Key', 'type' => 'text', 'placeholder' => 'e.g. pk_test_51... or pk_live_...'],
                    'secret_key'     => ['label' => 'Secret Key', 'type' => 'password', 'placeholder' => 'e.g. sk_test_51... or sk_live_...'],
                    'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'placeholder' => 'e.g. whsec_...'],
                ]
            ],
            'paypal' => [
                'name' => 'PayPal',
                'category' => 'International Cards',
                'description' => 'Global PayPal checkout and digital wallet',
                'fields' => [
                    'client_id' => ['label' => 'Client ID', 'type' => 'text', 'placeholder' => 'e.g. AXX4o_w-G789... (PayPal Client ID)'],
                    'secret'    => ['label' => 'Secret', 'type' => 'password', 'placeholder' => 'Enter PayPal Secret Key'],
                ]
            ],
            'binance_pay' => [
                'name' => 'Binance Pay',
                'category' => 'Crypto',
                'description' => 'Accept USDT, BTC, and cryptocurrency via Binance app',
                'fields' => [
                    'api_key'     => ['label' => 'API Key (BinancePay-Certificate-SN)', 'type' => 'text', 'placeholder' => 'e.g. Certificate SN or API Key'],
                    'secret_key'  => ['label' => 'Secret Key', 'type' => 'password', 'placeholder' => 'Enter Binance Pay Secret Key'],
                    'merchant_id' => ['label' => 'Merchant ID (Optional)', 'type' => 'text', 'placeholder' => 'e.g. 987654321'],
                    'return_url'  => ['label' => 'Return URL', 'type' => 'text', 'default' => '/payment/binance/return', 'placeholder' => '/payment/binance/return'],
                    'cancel_url'  => ['label' => 'Cancel URL', 'type' => 'text', 'default' => '/payment/binance/cancel', 'placeholder' => '/payment/binance/cancel'],
                ]
            ],
            'nowpayments' => [
                'name' => 'NOWPayments',
                'category' => 'Crypto',
                'description' => 'Accept 100+ cryptocurrencies (BTC, ETH, USDT, LTC, etc.)',
                'fields' => [
                    'api_key'     => ['label' => 'API Key', 'type' => 'text', 'placeholder' => 'e.g. ABCDEF1-2345678-90ABCDE-FGHIJKL'],
                    'ipn_secret'  => ['label' => 'IPN Secret Key', 'type' => 'password', 'placeholder' => 'Enter NOWPayments IPN Secret Key'],
                    'success_url' => ['label' => 'Success URL', 'type' => 'text', 'default' => '/payment/nowpayments/success', 'placeholder' => '/payment/nowpayments/success'],
                    'cancel_url'  => ['label' => 'Cancel URL', 'type' => 'text', 'default' => '/payment/nowpayments/cancel', 'placeholder' => '/payment/nowpayments/cancel'],
                    'ipn_url'     => ['label' => 'IPN Callback URL', 'type' => 'text', 'default' => '/payment/nowpayments/ipn', 'placeholder' => '/payment/nowpayments/ipn'],
                ]
            ],
            'bangla_qr' => [
                'name' => 'Bangla QR',
                'category' => 'Banks & QR',
                'description' => 'Bangladesh Bank Interoperable QR (bKash, Nagad, Rocket, Citytouch, Astha, etc.)',
                'fields' => [
                    'acquirer_bin'  => ['label' => 'Acquirer Bank BIN / Network ID', 'type' => 'text', 'default' => '000001', 'placeholder' => 'e.g. 000001'],
                    'merchant_id'   => ['label' => 'Merchant ID (MID)', 'type' => 'text', 'placeholder' => 'e.g. MID12345678'],
                    'terminal_id'   => ['label' => 'Terminal ID (TID)', 'type' => 'text', 'placeholder' => 'e.g. TID00001'],
                    'merchant_name' => ['label' => 'Merchant Trade Name', 'type' => 'text', 'default' => 'PayBridge Merchant', 'placeholder' => 'e.g. My ISP Business'],
                    'merchant_city' => ['label' => 'Merchant City', 'type' => 'text', 'default' => 'Dhaka', 'placeholder' => 'e.g. Dhaka'],
                    'mcc'           => ['label' => 'Merchant Category Code (MCC)', 'type' => 'text', 'default' => '5411', 'placeholder' => 'e.g. 5411'],
                    'api_url'       => ['label' => 'Acquirer Gateway API URL (Optional)', 'type' => 'text', 'placeholder' => 'e.g. https://bank.example.com/qr/create'],
                    'verify_url'    => ['label' => 'Acquirer Verify API URL (Optional)', 'type' => 'text', 'placeholder' => 'e.g. https://bank.example.com/qr/verify'],
                    'secret_key'    => ['label' => 'Webhook Secret / IPN Key (Optional)', 'type' => 'password', 'placeholder' => 'Enter Webhook / IPN Secret Key'],
                ]
            ],
        ];
    }

    /**
     * Ensure all 16 gateways exist in database with default configuration.
     */
    public static function seedDefaults(): void
    {
        $definitions = static::getFieldDefinitions();
        $order = 1;

        foreach ($definitions as $code => $info) {
            static::firstOrCreate(
                ['code' => $code],
                [
                    'name'        => $info['name'],
                    'category'    => $info['category'],
                    'is_active'   => false,
                    'is_sandbox'  => true,
                    'is_default'  => ($code === 'sslcommerz'),
                    'credentials' => [],
                    'sort_order'  => $order++,
                ]
            );
        }
    }
}
