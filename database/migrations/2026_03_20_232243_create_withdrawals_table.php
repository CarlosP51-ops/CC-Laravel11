<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('method'); // bank_transfer, paypal, mtn_momo, moov_money
            $table->string('gateway')->nullable(); // stripe, fedapay
            $table->json('payout_details')->nullable(); // IBAN, numéro mobile, etc.
            $table->enum('status', ['pending', 'pending_verification', 'processing', 'completed', 'rejected', 'cancelled'])->default('pending');
            $table->string('reference')->unique();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('risk_score')->default(0);
            $table->string('gateway_payout_id')->nullable(); // ID retour Stripe/FedaPay
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
