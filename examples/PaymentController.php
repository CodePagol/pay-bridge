<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayBridge\Payment\Facades\PayBridge;
use PayBridge\Payment\Exceptions\PaymentException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Initiate a payment via default or specific gateway.
     */
    public function checkout(Request $request)
    {
        $gateway = $request->query('gateway', 'sslcommerz'); // Or use your application logic
        
        $paymentData = [
            'amount' => 100.50,
            'currency' => 'BDT',
            'transaction_id' => Str::uuid()->toString(),
            'customer_name' => 'John Doe',
            'customer_email' => 'john.doe@example.com',
            'customer_phone' => '01711111111',
            'customer_address' => 'Dhaka, Bangladesh',
            'product_name' => 'Premium Subscription',
        ];

        try {
            // Using the Facade to dynamically pick driver and pay
            $response = PayBridge::driver($gateway)->pay($paymentData);

            if ($response['success'] && !empty($response['redirect_url'])) {
                return redirect()->away($response['redirect_url']);
            }

            return back()->with('error', $response['message']);

        } catch (PaymentException $e) {
            Log::error("Payment Error: " . $e->getMessage());
            return back()->with('error', "Could not process payment at this time.");
        }
    }

    /**
     * Handle Success redirect from Gateway
     */
    public function success(Request $request)
    {
        try {
            $gateway = $request->route('gateway') ?? $request->query('gateway');
            $response = PayBridge::driver($gateway)->verify($request->all());

            if (!$response['success']) {
                return redirect('/home')->with('error', 'Payment verification failed: ' . $response['message']);
            }

            $transactionId = $response['transaction_id'];

            /*
            |----------------------------------------------------------------------
            | CRITICAL FINANCIAL SECURITY CHECKS:
            | 1. Retrieve the order from database using $transactionId
            | 2. Verify payment amount and currency to prevent price manipulation
            | 3. Prevent double-crediting (idempotency)
            |----------------------------------------------------------------------
            $order = Order::where('transaction_id', $transactionId)->lockForUpdate()->first();

            if (!$order) {
                return redirect('/home')->with('error', 'Order not found.');
            }

            // Already paid? Skip processing to avoid double crediting
            if ($order->status === 'completed') {
                return redirect('/dashboard')->with('success', 'Payment already processed.');
            }

            // Validate amount paid against order total
            if (isset($response['amount']) && (float)$response['amount'] < (float)$order->total_amount) {
                Log::alert("Fraud Alert: Paid amount {$response['amount']} is less than expected {$order->total_amount} for Order: {$transactionId}");
                return redirect('/home')->with('error', 'Fraud detected: Paid amount mismatch.');
            }

            // Mark order as paid
            $order->update(['status' => 'completed', 'gateway_response' => $response['raw_response']]);
            */

            return redirect('/dashboard')->with('success', 'Payment verified and order fulfilled successfully!');

        } catch (PaymentException $e) {
            return redirect('/home')->with('error', $e->getMessage());
        }
    }

    /**
     * Handle Failure redirect
     */
    public function fail(Request $request)
    {
        return redirect('/home')->with('error', 'Payment was cancelled or failed.');
    }

    /**
     * Webhook/IPN endpoint
     */
    public function webhook(Request $request, $gateway)
    {
        try {
            // Strictly passes payload and headers to driver for cryptographic signature verification
            $response = PayBridge::driver($gateway)->webhook($request->all());

            if (!$response['success']) {
                Log::warning("Security Warning: Invalid webhook received for {$gateway}");
                return response()->json(['message' => 'Invalid Webhook Signature or Status'], 400);
            }

            $transactionId = $response['transaction_id'];

            /*
            |----------------------------------------------------------------------
            | CRITICAL FINANCIAL IDEMPOTENCY CHECK IN WEBHOOK:
            | Ensure database transaction with lock prevents race conditions
            | between customer redirect and asynchronous webhook callback.
            |----------------------------------------------------------------------
            DB::transaction(function () use ($transactionId, $response) {
                $order = Order::where('transaction_id', $transactionId)->lockForUpdate()->first();
                if ($order && $order->status !== 'completed') {
                    // Check amount match
                    if (!isset($response['amount']) || (float)$response['amount'] >= (float)$order->total_amount) {
                        $order->update(['status' => 'completed']);
                    }
                }
            });
            */

            return response()->json(['message' => 'Processed successfully'], 200);

        } catch (PaymentException $e) {
            Log::error("Webhook error for {$gateway}: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }
}
