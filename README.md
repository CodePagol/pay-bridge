<p align="center">
  <strong>PayBridge</strong> — Unified Payment Gateway for PHP & Laravel
</p>

<p align="center">
  <a href="https://packagist.org/packages/codepagol/pay-bridge"><img src="https://img.shields.io/badge/version-1.0.1-blue?style=flat-square" alt="Version"></a>
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-%5E8.2-8892BF?style=flat-square&logo=php" alt="PHP ^8.2"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?style=flat-square&logo=laravel" alt="Laravel 10|11|12"></a>
</p>

A modular, extensible payment processing package that combines **16 payment gateways** into one unified API system utilizing the Strategy Pattern. Works seamlessly across **Raw PHP, WordPress, CodeIgniter, Symfony**, and **Laravel** (with auto-discovery and interactive Admin Control Panel). Built with clean architecture, enterprise-ready features, and PSR-4 compatibility.

---

## 📑 Table of Contents

- [Supported Gateways](#-supported-gateways)
- [Installation Guide](#-installation-guide)
- [Quick Start — Raw PHP](#-quick-start--raw-php--wordpress--any-framework)
- [Quick Start — Laravel](#-quick-start--laravel)
- [API Reference](#-api-reference-pay-verify-refund-webhook)
- [Admin Control Panel](#%EF%B8%8F-admin-control-panel-no-code-setup)
- [Customer Checkout Integration](#-customer-checkout-integration)
- [Adding a Custom Gateway](#-adding-a-custom-gateway)
- [Events](#-events)
- [Security Checklist](#-security-checklist)
- [Production Checklist](#-production-checklist)
- [Configuration Reference](#%EF%B8%8F-configuration-reference)
- [Contributing](#-contributing)
- [License](#-license)
- [Changelog](#-changelog)

---

## 💳 Supported Gateways

| Category | Gateway | Driver Code | Status |
|:---------|:--------|:------------|:-------|
| **MFS** | bKash Tokenized | `bkash_tokenize` | ✅ Full |
| **MFS** | bKash PWG / URL Checkout | `bkash_pwg` | ✅ Full |
| **MFS** | Nagad | `nagad` | ✅ Full |
| **MFS** | DBBL Rocket | `rocket` | ✅ Full |
| **MFS** | UCB Upay | `upay` | ✅ Full |
| **QR** | Bangla QR (National Interoperable) | `bangla_qr` | ✅ Full |
| **Crypto** | Binance Pay | `binance_pay` | ✅ Full |
| **Crypto** | NOWPayments (100+ Cryptos) | `nowpayments` | ✅ Full |
| **Aggregator** | SSLCommerz | `sslcommerz` | ✅ Full |
| **Aggregator** | AamarPay | `aamarpay` | ✅ Full |
| **Aggregator** | SurjoPay | `surjopay` | ✅ Full |
| **Aggregator** | PortPay | `portpay` | ✅ Full |
| **Bank** | Sonali Pay | `sonalipay` | ✅ Full |
| **Bank** | Easy Payment System (EPS) | `eps` | ✅ Full |
| **International** | Stripe | `stripe` | ✅ Full |
| **International** | PayPal | `paypal` | ✅ Full |

---

## 📦 Installation Guide

### Requirements

- PHP `>= 8.2`
- PHP Extensions: `curl`, `openssl`, `json`, `bcmath` (recommended)
- Laravel 10, 11, or 12 (optional — works without Laravel)

### Step 1: Install via Composer

```bash
composer require codepagol/pay-bridge
```

For local/path-based development, add to your main project's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "path/to/PayBridge"
    }
],
"require": {
    "codepagol/pay-bridge": "@dev"
}
```

Then run `composer update codepagol/pay-bridge`.

### Step 2: Laravel Setup (Auto-Discovered)

The service provider `PayBridge\Payment\Providers\PaymentServiceProvider` and facade alias `PayBridge` are auto-discovered by Laravel.

```bash
# Publish configuration file
php artisan vendor:publish --tag=payment-config

# Run database migration
php artisan migrate

# (Optional) Publish admin views for customization
php artisan vendor:publish --tag=payment-views
```

### Step 3: Verify Installation

```bash
php artisan route:list | grep payment-gateways
```

You should see the `/admin/payment-gateways` routes registered.

---

## 🚀 Quick Start — Raw PHP / WordPress / Any Framework

```php
require_once 'vendor/autoload.php';

use PayBridge\Payment\PayBridge;

// Initialize any of the 16 gateways with its credentials array:
$gateway = PayBridge::make('bkash_tokenize', [
    'app_key'      => 'your_bkash_app_key',
    'app_secret'   => 'your_bkash_app_secret',
    'username'     => 'your_bkash_username',
    'password'     => 'your_bkash_password',
    'sandbox'      => true,
    'callback_url' => 'https://yourdomain.com/callback.php',
]);

// Initiate payment:
$response = $gateway->pay([
    'amount'         => 500.00,
    'transaction_id' => 'INV_' . time(),
    'currency'       => 'BDT',
]);

if ($response['success']) {
    header('Location: ' . $response['redirect_url']);
    exit;
} else {
    echo "Payment error: " . $response['message'];
}
```

---

## 🚀 Quick Start — Laravel

```php
use PayBridge\Payment\Facades\PayBridge;

// Uses the default driver from PAY_BRIDGE_DRIVER env variable
$response = PayBridge::driver()->pay([
    'amount'         => 100,
    'transaction_id' => uniqid(),
    'customer_name'  => 'John Doe',
    'customer_email' => 'john@example.com',
    'customer_phone' => '01711111111',
]);

if ($response['success']) {
    return redirect()->away($response['redirect_url']);
}

return back()->with('error', $response['message']);
```

Explicitly specify a gateway:

```php
// bKash Tokenize
$response = PayBridge::driver('bkash_tokenize')->pay([...]);

// Crypto Payment with Binance Pay
$response = PayBridge::driver('binance_pay')->pay([
    'amount'       => 50.00,
    'currency'     => 'USDT',
    'transaction_id' => uniqid('bp_'),
    'product_name' => 'VIP Membership',
]);

// Bangla QR (Scannable by bKash, Nagad, Rocket, etc.)
$response = PayBridge::driver('bangla_qr')->pay([
    'amount'         => 1250.00,
    'transaction_id' => uniqid('bqr_'),
    'product_name'   => 'Online Purchase',
]);
// Render QR: <img src="{{ $response['qr_image_url'] }}" alt="Bangla QR">
```

---

## 📘 API Reference: `pay()`, `verify()`, `refund()`, `webhook()`

Every gateway driver implements 4 standardized methods through the `PaymentGatewayInterface`:

### `pay(array $data): array` — Initiate Payment

```php
$response = $gateway->pay([
    'amount'         => 1250.00,
    'currency'       => 'BDT',          // or 'USD', 'USDT'
    'transaction_id' => 'TXN_' . uniqid(),
    'product_name'   => 'Order #1001',  // optional
    'customer_name'  => 'Jane Doe',     // optional
    'customer_email' => 'jane@test.com',// optional
    'customer_phone' => '01700000000',  // optional
]);
```

**Response:**
```php
[
    'success'        => true,
    'message'        => 'Payment initiated successfully',
    'transaction_id' => 'TXN_abc123',
    'redirect_url'   => 'https://gateway.example.com/checkout/...',
    'amount'         => null,
    'currency'       => null,
    'raw_response'   => [...],
]
```

### `verify(array $data): array` — Verify Payment

Called in your callback handler after the customer returns from the gateway:

```php
// Laravel
$result = PayBridge::driver('sslcommerz')->verify($request->all());

// Raw PHP
$result = $gateway->verify($_POST);

if ($result['success']) {
    // Payment verified — safe to fulfill order
    $verifiedAmount = $result['amount'];
    $currency       = $result['currency'];
    $transactionId  = $result['transaction_id'];
}
```

### `refund(string $transactionId): array` — Refund Payment

```php
$result = PayBridge::driver('sslcommerz')->refund('TXN_abc123');

if ($result['success']) {
    // Refund processed
}
```

### `webhook(array $payload): array` — Process Webhook / IPN

Used to process server-to-server notifications from gateways:

```php
// In your webhook controller
public function handleWebhook(Request $request, string $gateway)
{
    $result = PayBridge::driver($gateway)->webhook($request->all());

    if ($result['success']) {
        // Webhook signature verified — process the notification
        return response()->json(['status' => 'ok']);
    }

    return response()->json(['error' => $result['message']], 400);
}
```

> **Important:** Webhook routes must be excluded from CSRF verification. See [Troubleshooting](#-security-checklist).

---

## 🖥️ Admin Control Panel (No-Code Setup)

PayBridge includes a complete, browser-based Admin Settings UI so non-developers can manage gateways without touching code or `.env`:

1. Visit **`/admin/payment-gateways`** in your browser.
2. **Toggle Gateways On/Off**: One click to enable or disable any gateway.
3. **Configure API Keys & Passwords**: Input Store IDs, App Keys, and Secrets directly. All credentials are automatically encrypted in the database.
4. **Sandbox / Live Toggle**: Switch between testing and production per gateway.
5. **Set Primary Default**: Mark your default gateway with a single click.

### Showing Active Gateways on Customer Checkout

```php
use PayBridge\Payment\Facades\PayBridge;

// Returns only gateways where is_active = true
$activeGateways = PayBridge::getActiveGateways();
```

### 🔒 Securing the Admin Panel

By default, PayBridge enforces `['web', 'auth']` middleware. Customize in `config/payment.php`:

```php
'admin' => [
    'enabled'    => true,                // Set false to disable admin UI entirely
    'prefix'     => env('PAY_BRIDGE_ADMIN_PREFIX', 'admin/payment-gateways'),
    'middleware' => ['web', 'auth'],
],
```

#### Option A: Spatie Permission
```php
'middleware' => ['web', 'auth', 'role:admin|super-admin'],
```

#### Option B: Laravel Gates
```php
'middleware' => ['web', 'auth', 'can:manage-payments'],
```

#### Option C: Custom Middleware
```php
'middleware' => ['web', 'auth', 'is_admin'],
```

#### Option D: Dedicated Admin Guard (Multi-Auth)
```php
'middleware' => ['web', 'auth:admin'],
```

#### Customizing the URL Prefix
```env
PAY_BRIDGE_ADMIN_PREFIX=dashboard/settings/payments
```

#### Disabling the Admin Panel
For headless/API-only projects:
```env
PAY_BRIDGE_ADMIN_ENABLED=false
```

---

## 🛒 Customer Checkout Integration

### Fetching Active Gateways in Controller

```php
use PayBridge\Payment\Facades\PayBridge;

class CheckoutController extends Controller
{
    public function showCheckout()
    {
        $activeGateways = PayBridge::getActiveGateways();

        return view('checkout', [
            'gateways'   => $activeGateways,
            'orderTotal' => 1250.00,
        ]);
    }
}
```

### Rendering in Blade

```html
<form action="{{ route('checkout.process') }}" method="POST">
    @csrf
    <h3>Select Payment Method:</h3>

    @foreach($gateways as $gateway)
        <label>
            <input type="radio" name="payment_gateway"
                   value="{{ $gateway['code'] }}"
                   {{ $gateway['is_default'] ? 'checked' : '' }}>
            <strong>{{ $gateway['name'] }}</strong>
            <span>{{ $gateway['category'] }}</span>
        </label>
    @endforeach

    <button type="submit">Proceed to Payment (৳1250)</button>
</form>
```

### Processing the Payment

```php
public function processCheckout(Request $request)
{
    $gateway = $request->input('payment_gateway', 'sslcommerz');

    $response = PayBridge::driver($gateway)->pay([
        'amount'         => 1250.00,
        'currency'       => ($gateway === 'binance_pay') ? 'USDT' : 'BDT',
        'transaction_id' => 'TXN_' . uniqid(),
        'product_name'   => 'Order #1001',
        'customer_name'  => auth()->user()->name ?? 'Customer',
        'customer_email' => auth()->user()->email ?? 'customer@example.com',
        'customer_phone' => '01711111111',
    ]);

    if (!$response['success']) {
        return back()->with('error', $response['message']);
    }

    // Bangla QR: Display QR code directly
    if ($gateway === 'bangla_qr' && !empty($response['qr_image_url'])) {
        return view('checkout.bangla-qr', [
            'qrImageUrl' => $response['qr_image_url'],
            'tranId'     => $response['transaction_id'],
            'amount'     => 1250.00,
        ]);
    }

    // Standard: Redirect to hosted checkout
    return redirect()->away($response['redirect_url']);
}
```

---

## 🔌 Adding a Custom Gateway

1. Create a class extending `PayBridge\Payment\Drivers\AbstractGatewayDriver`:

```php
namespace App\Gateway;

use PayBridge\Payment\Drivers\AbstractGatewayDriver;

class MyCustomDriver extends AbstractGatewayDriver
{
    public function pay(array $data): array
    {
        // Your implementation
        return $this->formatResponse(true, 'Payment initiated', $data['transaction_id'] ?? null, 'https://...');
    }

    public function verify(array $data): array { /* ... */ }
    public function refund(string $transactionId): array { /* ... */ }
    public function webhook(array $payload): array { /* ... */ }
}
```

2. Register in `config/payment.php`:

```php
'custom_gateway' => [
    'class'   => \App\Gateway\MyCustomDriver::class,
    'api_key' => env('CUSTOM_GATEWAY_API_KEY', ''),
]
```

3. Use it:

```php
$response = PayBridge::driver('custom_gateway')->pay([...]);
```

---

## 📡 Events

PayBridge fires standard Laravel events during transactions:

| Event | Fired When |
|:------|:-----------|
| `PayBridge\Payment\Events\PaymentSuccess` | Payment succeeds (with `transactionId` & `payload`) |
| `PayBridge\Payment\Events\PaymentFailed` | Payment fails (with `transactionId`, `error` & `payload`) |

### Listening to Events

```php
// In EventServiceProvider or Listener
use PayBridge\Payment\Events\PaymentSuccess;
use PayBridge\Payment\Events\PaymentFailed;

protected $listen = [
    PaymentSuccess::class => [
        \App\Listeners\LogSuccessfulPayment::class,
    ],
    PaymentFailed::class => [
        \App\Listeners\LogFailedPayment::class,
    ],
];
```

```php
// App\Listeners\LogSuccessfulPayment.php
class LogSuccessfulPayment
{
    public function handle(PaymentSuccess $event): void
    {
        \Log::info("Payment success: {$event->transactionId}", $event->payload);

        // Update order status, send receipt email, etc.
    }
}
```

> Events are dispatched safely — they gracefully degrade in non-Laravel environments.

---

## 🔒 Security Checklist

- [ ] **CSRF Exclusion**: Add callback/webhook URLs to the `$except` array in `VerifyCsrfToken` since gateways send external POST requests.
- [ ] **Webhook Verification**: Always verify webhook signatures via the `webhook()` driver method to prevent spoofed notifications.
- [ ] **Price Tampering**: Always verify the returned amount matches your database record. Never trust client-side amounts.
- [ ] **HTTPS Only**: Ensure all live endpoints use HTTPS. Most gateways reject non-HTTPS webhook URLs in production.
- [ ] **Data Validation**: Validate all incoming data before passing to `->pay(...)`.
- [ ] **APP_KEY**: Back up your Laravel `APP_KEY` — it's used to encrypt database credentials.

---

## 🚀 Production Checklist

- [ ] Set `PAY_BRIDGE_DRIVER` in `.env`.
- [ ] Switch sandbox to `false` for your active gateway (e.g., `SSLCZ_SANDBOX=false`).
- [ ] Verify real API Keys and Secret Keys are accurately configured.
- [ ] Ensure SSL verification is active (`'ssl_verify' => true` is the default).
- [ ] Test end-to-end with a small real transaction.
- [ ] Protect the Admin Panel with role-based middleware for production.
- [ ] Set up idempotent webhook handlers with `lockForUpdate()` to prevent double-processing.

---

## ⚙️ Configuration Reference

All gateway credentials can be configured via:

1. **Admin Control Panel** (database-first, highest priority)
2. **`.env` file** (fallback when no database record exists)
3. **`config/payment.php`** (hardcoded defaults)

### Key `.env` Variables

```env
# Default Payment Gateway
PAY_BRIDGE_DRIVER=sslcommerz

# Admin Panel
PAY_BRIDGE_ADMIN_PREFIX=admin/payment-gateways
PAY_BRIDGE_ADMIN_ENABLED=true

# Gateway Credentials (examples)
SSLCZ_STORE_ID=your_store_id
SSLCZ_STORE_PASSWORD=your_store_password
SSLCZ_SANDBOX=true

BKASH_TOKENIZE_APP_KEY=your_app_key
BKASH_TOKENIZE_APP_SECRET=your_app_secret
BKASH_TOKENIZE_USERNAME=your_username
BKASH_TOKENIZE_PASSWORD=your_password
BKASH_TOKENIZE_SANDBOX=true

STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...

BINANCE_PAY_API_KEY=your_cert_sn
BINANCE_PAY_SECRET_KEY=your_secret
```

See [`config/payment.php`](config/payment.php) for the complete list of all 16 gateway variables.

---

## 🤝 Contributing

Contributions are welcome! Here's how to get started:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-new-gateway`
3. Commit your changes: `git commit -m "Add MyGateway driver"`
4. Push to the branch: `git push origin feature/my-new-gateway`
5. Open a Pull Request

### Guidelines

- Follow PSR-4 autoloading and PSR-12 coding standards
- All new gateway drivers must extend `AbstractGatewayDriver` and implement `PaymentGatewayInterface`
- Add gateway field definitions to `PaymentGatewaySetting::getFieldDefinitions()`
- Register the driver in both `PayBridge::$drivers` (standalone) and `PaymentManager` (Laravel)
- Write clear docblocks for all public methods

---

## 📄 License

PayBridge is open-source software licensed under the [MIT License](LICENSE).

---

## 📋 Changelog

### v1.0.1 — Bug Fixes, Documentation & Reliability Update

- **Bug Fix**: Removed duplicate `surjopay` config block in `config/payment.php`.
- **Dependency Optimization**: Removed unused `ramsey/uuid` dependency from `composer.json` and updated lockfile.
- **Facade Alignment**: Standardized Facade namespace and class as `PayBridge\Payment\Facades\PayBridge`.
- **Admin Control**: Added `PAY_BRIDGE_ADMIN_ENABLED` config flag to conditionally load admin routes and views.
- **Import Cleanup**: Removed unused `Illuminate\Support\Str` imports from driver classes.
- **Documentation**: Completed full documentation suite (all 7 guide chapters + updated README).

### v1.0.0 — Initial Release

- 16 payment gateways with unified `pay()`, `verify()`, `refund()`, `webhook()` API
- No-code Admin Control Panel with encrypted credential storage
- Support for Raw PHP, WordPress, CodeIgniter, Symfony, and Laravel
- Dynamic customer checkout with Bangla QR display
- Cryptocurrency payments via Binance Pay and NOWPayments
- Bank-grade security: encrypted credentials, constant-time signature verification, SSL enforcement
- Configurable admin panel with role-based access control

---

## 📚 Full Documentation

For detailed guides on each gateway, security best practices, and troubleshooting:

👉 **[PayBridge Documentation Portal](https://paybridge.codepagol.com)** | **[`docs/`](docs/README.md)**
