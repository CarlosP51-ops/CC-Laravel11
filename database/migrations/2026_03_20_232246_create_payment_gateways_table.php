<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Stripe, FedaPay
            $table->string('slug')->unique(); // stripe, fedapay
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->text('public_key')->nullable();
            $table->text('secret_key')->nullable(); // chiffré
            $table->text('webhook_secret')->nullable();
            $table->json('supported_methods')->nullable(); // ['card', 'mtn_momo', 'moov_money']
            $table->json('settings')->nullable(); // config additionnelle
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
