<?php

namespace PayBridge\Payment\Providers;

use Illuminate\Support\ServiceProvider;
use PayBridge\Payment\PaymentManager;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/payment.php', 'payment'
        );

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager($app);
        });

        $this->app->alias(PaymentManager::class, 'paybridge');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Only register admin routes and views when the Admin Control Panel is enabled
        if ($this->app['config']->get('payment.admin.enabled', true)) {
            // Load package views under 'paybridge' namespace
            $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'paybridge');

            // Load package routes
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__ . '/../../config/payment.php' => config_path('payment.php'),
            ], 'payment-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'payment-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/paybridge'),
            ], 'payment-views');
        }
    }
}
