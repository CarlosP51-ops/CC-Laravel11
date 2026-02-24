<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('payload');
            $table->integer('last_activity');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};