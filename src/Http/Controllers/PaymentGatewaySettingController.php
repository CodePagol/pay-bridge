<?php

namespace PayBridge\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PayBridge\Payment\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Schema;

class PaymentGatewaySettingController extends Controller
{
    /**
     * Display the Admin Payment Gateways Control Panel.
     */
    public function index()
    {
        // Automatically seed default gateway list if table is empty
        if (Schema::hasTable('payment_gateway_settings')) {
            PaymentGatewaySetting::seedDefaults();
            $gateways = PaymentGatewaySetting::orderBy('sort_order', 'asc')->get();
        } else {
            $gateways = collect();
        }

        $definitions = PaymentGatewaySetting::getFieldDefinitions();

        // Group by category
        $groupedGateways = $gateways->groupBy('category');

        return view('paybridge::admin.gateways', [
            'groupedGateways' => $groupedGateways,
            'gateways'        => $gateways,
            'definitions'     => $definitions,
        ]);
    }

    /**
     * Update settings and credentials for a specific gateway.
     */
    public function update(Request $request, string $code)
    {
        $gateway = PaymentGatewaySetting::where('code', $code)->firstOrFail();
        $definitions = PaymentGatewaySetting::getFieldDefinitions()[$code]['fields'] ?? [];

        $existing = $gateway->credentials;
        $credentials = is_array($existing) ? $existing : [];

        foreach ($definitions as $key => $field) {
            if ($request->has("credentials.{$key}")) {
                $val = $request->input("credentials.{$key}");
                // If password field is left empty on update, retain existing credential
                if ($field['type'] === 'password' && (is_null($val) || $val === '')) {
                    continue;
                }
                $credentials[$key] = $val;
            }
        }

        $gateway->is_active = $request->boolean('is_active');
        $gateway->is_sandbox = $request->boolean('is_sandbox');
        $gateway->credentials = $credentials;

        if ($request->boolean('is_default')) {
            $this->makeDefault($code);
        } else {
            $gateway->is_default = false;
        }

        $gateway->save();

        return redirect()->back()->with('success', "Settings for {$gateway->name} updated successfully!");
    }

    /**
     * Quick toggle gateway active status via AJAX / POST.
     */
    public function toggle(Request $request, string $code)
    {
        $gateway = PaymentGatewaySetting::where('code', $code)->firstOrFail();
        $gateway->is_active = !$gateway->is_active;
        $gateway->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'is_active' => $gateway->is_active,
                'message'   => "{$gateway->name} is now " . ($gateway->is_active ? 'Active' : 'Inactive')
            ]);
        }

        return redirect()->back()->with('success', "{$gateway->name} status toggled.");
    }

    /**
     * Set a gateway as the default system gateway (or unset if already default).
     */
    public function setDefault(Request $request, string $code)
    {
        $gateway = PaymentGatewaySetting::where('code', $code)->firstOrFail();

        if ($gateway->is_default) {
            $gateway->update(['is_default' => false]);
            $msg = "{$gateway->name} is no longer the default gateway.";
        } else {
            $this->makeDefault($code);
            $msg = "{$gateway->name} set as default gateway.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Internal helper to make a gateway default.
     */
    protected function makeDefault(string $code): void
    {
        PaymentGatewaySetting::where('is_default', true)->update(['is_default' => false]);
        PaymentGatewaySetting::where('code', $code)->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * JSON API to retrieve active gateways for customer checkout pages.
     */
    public function activeGateways()
    {
        $active = PaymentGatewaySetting::active()->get(['code', 'name', 'category', 'is_default', 'is_sandbox']);

        return response()->json([
            'success'  => true,
            'gateways' => $active
        ]);
    }
}
