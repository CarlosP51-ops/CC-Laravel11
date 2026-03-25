<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('admin_notifications');

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();

            // Type de notification (détermine l'icône, la couleur, le lien)
            $table->string('type'); // new_order, new_vendor, withdrawal_request, product_pending,
                                    // bad_review, vendor_suspended, new_client, low_stock,
                                    // payment_failed, product_reported, seller_inactive, system

            // Contenu
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();

            // Lien de redirection (route frontend)
            $table->string('link')->nullable(); // ex: /admin/payments?tab=withdrawals

            // Entité source (polymorphique léger)
            $table->string('entity_type')->nullable(); // order, seller, product, user, withdrawal, review
            $table->unsignedBigInteger('entity_id')->nullable();

            // Données supplémentaires (JSON)
            $table->json('meta')->nullable();

            // Lecture
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['is_read', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
