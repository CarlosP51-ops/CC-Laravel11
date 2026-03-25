<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Ajouter seller_id pour lier directement au vendeur
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('sellers')->nullOnDelete();
            
            // Ajouter les champs de suivi
            $table->string('tracking_number')->nullable()->after('payment_status');
            $table->string('carrier')->nullable()->after('tracking_number');
            $table->string('tracking_url')->nullable()->after('carrier');
            
            // Renommer 'total' en 'total_amount' pour cohérence
            $table->renameColumn('total', 'total_amount');
        });

        // Modifier l'enum status pour ajouter 'processing'
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'tracking_number', 'carrier', 'tracking_url']);
            $table->renameColumn('total_amount', 'total');
        });

        // Revenir à l'ancien enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
    }
};
