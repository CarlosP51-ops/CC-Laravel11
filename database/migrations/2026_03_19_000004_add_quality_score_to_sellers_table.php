<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->integer('quality_score')->default(70)->after('is_active');
            $table->integer('warnings_count')->default(0)->after('quality_score');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['quality_score', 'warnings_count']);
        });
    }
};
