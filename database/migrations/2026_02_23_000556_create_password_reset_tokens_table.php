<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePasswordResetTokensTable extends Migration
{
    public function up()
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->index(); // Colonne pour l'email de l'utilisateur
            $table->string('token'); // Colonne pour le jeton de réinitialisation
            $table->timestamp('created_at')->nullable(); // Colonne pour la date de création
        });
    }

    public function down()
    {
        Schema::dropIfExists('password_reset_tokens'); // Supprime la table si besoin
    }
}
