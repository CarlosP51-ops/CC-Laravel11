<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // le vendeur (user)
            $table->string('type'); // new_order, order_cancelled, product_approved, product_rejected,
                                    // new_review, withdrawal_approved, withdrawal_rejected,
                                    // low_stock, account_suspended, account_reactivated,
                                    // new_message, newsletter
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->json('meta')->nullable(); // données supplémentaires (order_id, product_id, etc.)
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_notifications');
    }
};
