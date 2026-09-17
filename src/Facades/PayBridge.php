<?php

namespace PayBridge\Payment\Facades;

use Illuminate\Support\Facades\Facade;
use PayBridge\Payment\PaymentManager;

/**
 * PayBridge Laravel Facade.
 *
 * @method static \PayBridge\Payment\Contracts\PaymentGatewayInterface driver(string|null $driver = null)
 * @method static array getActiveGateways()
 * @method static array getSupportedDrivers()
 * @method static array pay(array $data)
 * @method static array verify(array $data)
 * @method static array refund(string $transactionId)
 * @method static array webhook(array $data)
 * 
 * @see \PayBridge\Payment\PaymentManager
 */
class PayBridge extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return PaymentManager::class;
    }
}
