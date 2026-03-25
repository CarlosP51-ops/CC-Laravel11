<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // true pour tous sauf les vendors qui sont mis à false à l'inscription
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Mettre is_active = false pour tous les vendors existants non encore activés
        // (ceux dont le seller associé a is_active = false)
        DB::statement("
            UPDATE users
            SET is_active = false
            WHERE role = 'vendor'
            AND id IN (
                SELECT user_id FROM sellers WHERE is_active = false
            )
        ");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
