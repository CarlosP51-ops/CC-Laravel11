<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('payment_method'); // stripe, fedapay
            $table->string('gateway_transaction_id')->nullable()->after('gateway'); // ID Stripe/FedaPay
            $table->decimal('fee', 10, 2)->default(0)->after('amount'); // frais gateway
            $table->json('gateway_response')->nullable()->after('gateway_transaction_id'); // réponse brute
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_transaction_id', 'fee', 'gateway_response']);
        });
    }
};
