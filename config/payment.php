<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Supported: "sslcommerz", "stripe", "paypal", "bkash_tokenize", "bkash_pwg", "surjopay", "aamarpay",
    |            "nagad", "sonalipay", "rocket", "upay", "portpay", "eps", "binance_pay", "nowpayments", "bangla_qr"
    |
    */

    'default' => env('PAY_BRIDGE_DRIVER', 'sslcommerz'),

    /*
    |--------------------------------------------------------------------------
    | Payment Drivers Setup
    |--------------------------------------------------------------------------
    |
    | Gateways map to shared generic PAY_BRIDGE variables in .env so you
    | don't need dozens of specific variables for a single active gateway.
    |
    */

    'drivers' => [

        'sslcommerz' => [
            'store_id'     => env('SSLCZ_STORE_ID', ''),
            'store_password' => env('SSLCZ_STORE_PASSWORD', ''),
            'sandbox'      => env('SSLCZ_SANDBOX', true),
            'success_url'  => env('SSLCZ_SUCCESS_URL', '/payment/sslcommerz/success'),
            'fail_url'     => env('SSLCZ_FAIL_URL', '/payment/sslcommerz/fail'),
            'cancel_url'   => env('SSLCZ_CANCEL_URL', '/payment/sslcommerz/cancel'),
            'ipn_url'      => env('SSLCZ_IPN_URL', '/payment/sslcommerz/ipn'),
        ],

        'stripe' => [
            'public_key'   => env('STRIPE_PUBLIC_KEY', ''),
            'secret_key'   => env('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
            'success_url'  => env('STRIPE_SUCCESS_URL', '/payment/stripe/success'),
            'cancel_url'   => env('STRIPE_CANCEL_URL', '/payment/stripe/cancel'),
        ],

        'paypal' => [
            'client_id'    => env('PAYPAL_CLIENT_ID', ''),
            'secret'       => env('PAYPAL_SECRET', ''),
            'sandbox'      => env('PAYPAL_SANDBOX', true),
            'success_url'  => env('PAYPAL_SUCCESS_URL', '/payment/paypal/success'),
            'cancel_url'   => env('PAYPAL_CANCEL_URL', '/payment/paypal/cancel'),
        ],

        'bkash_tokenize' => [
            'app_key'      => env('BKASH_TOKENIZE_APP_KEY', ''),
            'app_secret'   => env('BKASH_TOKENIZE_APP_SECRET', ''),
            'username'     => env('BKASH_TOKENIZE_USERNAME', ''),
            'password'     => env('BKASH_TOKENIZE_PASSWORD', ''),
            'sandbox'      => env('BKASH_TOKENIZE_SANDBOX', true),
            'callback_url' => env('BKASH_TOKENIZE_CALLBACK_URL', '/payment/bkash_tokenize/callback'),
        ],

        'bkash_pwg' => [
            'app_key'      => env('BKASH_PWG_APP_KEY', ''),
            'app_secret'   => env('BKASH_PWG_APP_SECRET', ''),
            'username'     => env('BKASH_PWG_USERNAME', ''),
            'password'     => env('BKASH_PWG_PASSWORD', ''),
            'sandbox'      => env('BKASH_PWG_SANDBOX', true),
            'callback_url' => env('BKASH_PWG_CALLBACK_URL', '/payment/bkash_pwg/callback'),
        ],

        'surjopay' => [
            'merchant_name' => env('SURJOPAY_MERCHANT_NAME', ''),
            'merchant_password' => env('SURJOPAY_MERCHANT_PASSWORD', ''),
            'merchant_prefix' => env('SURJOPAY_MERCHANT_PREFIX', ''),
            'sandbox'      => env('SURJOPAY_SANDBOX', true),
            'success_url'  => env('SURJOPAY_SUCCESS_URL', '/payment/surjopay/success'),
            'cancel_url'   => env('SURJOPAY_CANCEL_URL', '/payment/surjopay/cancel'),
        ],

        'aamarpay' => [
            'store_id'     => env('AAMARPAY_STORE_ID', ''),
            'signature_key'=> env('AAMARPAY_SIGNATURE_KEY', ''),
            'sandbox'      => env('AAMARPAY_SANDBOX', true),
            'success_url'  => env('AAMARPAY_SUCCESS_URL', '/payment/aamarpay/success'),
            'fail_url'     => env('AAMARPAY_FAIL_URL', '/payment/aamarpay/fail'),
            'cancel_url'   => env('AAMARPAY_CANCEL_URL', '/payment/aamarpay/cancel'),
        ],

        'nagad' => [
            'merchant_id'  => env('NAGAD_MERCHANT_ID', ''),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER', ''),
            'public_key'   => env('NAGAD_PUBLIC_KEY', ''),
            'private_key'  => env('NAGAD_PRIVATE_KEY', ''),
            'sandbox'      => env('NAGAD_SANDBOX', true),
            'callback_url' => env('NAGAD_CALLBACK_URL', '/payment/nagad/callback'),
        ],

        'sonalipay' => [
            'merchant_id'  => env('SONALIPAY_MERCHANT_ID', ''),
            'sandbox'      => env('SONALIPAY_SANDBOX', true),
            'callback_url' => env('SONALIPAY_CALLBACK_URL', '/payment/sonalipay/callback'),
        ],

        'rocket' => [
            'merchant_id'  => env('ROCKET_MERCHANT_ID', ''),
            'sandbox'      => env('ROCKET_SANDBOX', true),
            'callback_url' => env('ROCKET_CALLBACK_URL', '/payment/rocket/callback'),
        ],

        'upay' => [
            'merchant_id'  => env('UPAY_MERCHANT_ID', ''),
            'merchant_key' => env('UPAY_MERCHANT_KEY', ''),
            'sandbox'      => env('UPAY_SANDBOX', true),
            'callback_url' => env('UPAY_CALLBACK_URL', '/payment/upay/callback'),
        ],

        'portpay' => [
            'app_key'      => env('PORTPAY_APP_KEY', ''),
            'secret_key'   => env('PORTPAY_SECRET_KEY', ''),
            'sandbox'      => env('PORTPAY_SANDBOX', true),
            'callback_url' => env('PORTPAY_CALLBACK_URL', '/payment/portpay/callback'),
        ],

        'eps' => [
            'merchant_id'  => env('EPS_MERCHANT_ID', ''),
            'sandbox'      => env('EPS_SANDBOX', true),
            'callback_url' => env('EPS_CALLBACK_URL', '/payment/eps/callback'),
            'fail_url'     => env('EPS_FAIL_URL', '/payment/eps/fail'),
            'cancel_url'   => env('EPS_CANCEL_URL', '/payment/eps/cancel'),
        ],

        'binance_pay' => [
            'api_key'      => env('BINANCE_PAY_API_KEY', ''),
            'secret_key'   => env('BINANCE_PAY_SECRET_KEY', ''),
            'merchant_id'  => env('BINANCE_PAY_MERCHANT_ID', ''),
            'sandbox'      => env('BINANCE_PAY_SANDBOX', true),
            'return_url'   => env('BINANCE_PAY_RETURN_URL', '/payment/binance/return'),
            'cancel_url'   => env('BINANCE_PAY_CANCEL_URL', '/payment/binance/cancel'),
        ],

        'nowpayments' => [
            'api_key'      => env('NOWPAYMENTS_API_KEY', ''),
            'ipn_secret'   => env('NOWPAYMENTS_IPN_SECRET', ''),
            'sandbox'      => env('NOWPAYMENTS_SANDBOX', true),
            'success_url'  => env('NOWPAYMENTS_SUCCESS_URL', '/payment/nowpayments/success'),
            'cancel_url'   => env('NOWPAYMENTS_CANCEL_URL', '/payment/nowpayments/cancel'),
            'ipn_url'      => env('NOWPAYMENTS_IPN_URL', '/payment/nowpayments/ipn'),
        ],

        'bangla_qr' => [
            'acquirer_bin'  => env('BANGLA_QR_ACQUIRER_BIN', '000001'),
            'merchant_id'   => env('BANGLA_QR_MERCHANT_ID', ''),
            'terminal_id'   => env('BANGLA_QR_TERMINAL_ID', ''),
            'merchant_name' => env('BANGLA_QR_MERCHANT_NAME', 'PayBridge Merchant'),
            'merchant_city' => env('BANGLA_QR_MERCHANT_CITY', 'Dhaka'),
            'mcc'           => env('BANGLA_QR_MCC', '5411'),
            'sandbox'       => env('BANGLA_QR_SANDBOX', true),
            'api_url'       => env('BANGLA_QR_API_URL', ''),
            'verify_url'    => env('BANGLA_QR_VERIFY_URL', ''),
            'callback_url'  => env('BANGLA_QR_CALLBACK_URL', '/payment/banglaqr/callback'),
            'secret_key'    => env('BANGLA_QR_SECRET_KEY', ''),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Control Panel Security & Routing
    |--------------------------------------------------------------------------
    |
    | Strictly protect the control panel from unauthorized public access.
    | By default, 'auth' middleware is enforced. You can customize this
    | to match your app's admin guard (e.g., ['web', 'auth:admin', 'role:superadmin']).
    |
    | Set 'enabled' to false to disable the web-based Admin Control Panel
    | entirely (useful for headless/API-only projects that manage credentials
    | exclusively via .env or config files).
    |
    */

    'admin' => [
        'enabled'    => env('PAY_BRIDGE_ADMIN_ENABLED', true),
        'prefix'     => env('PAY_BRIDGE_ADMIN_PREFIX', 'admin/payment-gateways'),
        'middleware' => ['web', 'auth'],
    ],

];
