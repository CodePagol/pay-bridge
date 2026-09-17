<?php

/**
 * PayBridge - Raw PHP / Framework-Agnostic Usage Example
 *
 * Works in Raw PHP, WordPress, CodeIgniter, Symfony, Slim, etc.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PayBridge\Payment\PayBridge;

// -------------------------------------------------------------
// Example 1: bKash Tokenized Checkout (Raw PHP)
// -------------------------------------------------------------
$bkash = PayBridge::make('bkash_tokenize', [
    'app_key'      => 'your_bkash_app_key',
    'app_secret'   => 'your_bkash_app_secret',
    'username'     => 'your_bkash_username',
    'password'     => 'your_bkash_password',
    'sandbox'      => true,
    'callback_url' => 'https://yourwebsite.com/payment/callback.php',
]);

$response = $bkash->pay([
    'amount'         => 120.00,
    'transaction_id' => 'INV_' . time(),
    'customer_phone' => '01700000000',
]);

if ($response['success']) {
    // Redirect customer to the gateway checkout page
    header('Location: ' . $response['redirect_url']);
    exit;
} else {
    echo "Payment Error: " . $response['message'];
}


// -------------------------------------------------------------
// Example 2: Binance Pay (Crypto) Checkout (Raw PHP)
// -------------------------------------------------------------
$binance = PayBridge::make('binance_pay', [
    'api_key'    => 'your_binance_api_key',
    'secret_key' => 'your_binance_secret_key',
]);

$binanceResponse = $binance->pay([
    'amount'         => 25.50,
    'currency'       => 'USDT',
    'transaction_id' => 'BP_' . uniqid(),
    'product_name'   => 'Digital Goods Order #101',
]);

if ($binanceResponse['success']) {
    header('Location: ' . $binanceResponse['redirect_url']);
    exit;
}


// -------------------------------------------------------------
// Example 3: Bangla QR Generation (Raw PHP)
// -------------------------------------------------------------
$banglaQr = PayBridge::make('bangla_qr', [
    'merchant_id'   => '01700000000',
    'merchant_name' => 'My Grocery Store',
    'city'          => 'Dhaka',
    'postal_code'   => '1212',
]);

$qrResponse = $banglaQr->pay([
    'amount'         => 750.00,
    'transaction_id' => 'BQR_' . time(),
]);

if ($qrResponse['success']) {
    // Render QR Code image directly in HTML:
    echo '<img src="' . htmlspecialchars($qrResponse['qr_image_url']) . '" alt="Bangla QR" />';
}
