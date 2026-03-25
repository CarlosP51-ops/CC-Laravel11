<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            // participant_1_id est toujours le plus petit des deux ids
            $table->foreignId('participant_1_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_2_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // Une seule conversation possible entre deux utilisateurs
            $table->unique(['participant_1_id', 'participant_2_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
