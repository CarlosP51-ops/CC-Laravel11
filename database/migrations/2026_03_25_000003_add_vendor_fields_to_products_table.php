<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('is_active');
            }
            if (!Schema::hasColumn('products', 'is_digital')) {
                $table->boolean('is_digital')->default(false)->after('status');
            }
            if (!Schema::hasColumn('products', 'digital_file_path')) {
                $table->string('digital_file_path')->nullable()->after('is_digital');
            }
            if (!Schema::hasColumn('products', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable()->after('digital_file_path');
            }
            if (!Schema::hasColumn('products', 'dimensions')) {
                $table->string('dimensions')->nullable()->after('weight');
            }
            if (!Schema::hasColumn('products', 'tags')) {
                $table->text('tags')->nullable()->after('dimensions');
            }
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->unique()->nullable()->after('slug');
            }
            if (!Schema::hasColumn('products', 'subcategory_id')) {
                $table->foreignId('subcategory_id')->nullable()->constrained('categories')->onDelete('set null')->after('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_digital', 'digital_file_path', 'weight', 'dimensions', 'tags']);
            if (Schema::hasColumn('products', 'sku')) $table->dropColumn('sku');
            if (Schema::hasColumn('products', 'subcategory_id')) {
                $table->dropForeign(['subcategory_id']);
                $table->dropColumn('subcategory_id');
            }
        });
    }
};
