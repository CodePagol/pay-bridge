<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'bkash_tokenize', 'binance_pay', 'sslcommerz'
            $table->string('name');            // e.g., 'bKash Tokenized', 'Binance Pay'
            $table->string('category')->default('mfs'); // mfs, crypto, card, aggregator, bank
            $table->boolean('is_active')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable(); // JSON stored (encrypted for security)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
