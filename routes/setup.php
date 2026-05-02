<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Route de setup temporaire — SUPPRIMER APRÈS UTILISATION
 * Protégée par un token secret
 */
Route::get('/setup/{token}', function (string $token) {

    // Token secret — change cette valeur avant de pusher
    $secret = env('SETUP_TOKEN', 'cs-setup-2024-secret');

    if ($token !== $secret) {
        abort(403, 'Token invalide.');
    }

    $action = request('action', 'status');
    $output = [];

    if ($action === 'clear-and-seed') {
        // 1. Vider toutes les tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key    = "Tables_in_{$dbName}";
        $skip   = ['migrations', 'password_reset_tokens', 'personal_access_tokens'];

        foreach ($tables as $table) {
            $name = $table->$key;
            if (in_array($name, $skip)) continue;
            DB::table($name)->truncate();
            $output[] = "✓ {$name} vidée";
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 2. Créer l'admin
        Artisan::call('db:seed', ['--class' => 'AdminSeeder', '--force' => true]);
        $output[] = Artisan::output();

        // 3. Créer les catégories
        Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
        $output[] = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Base de données réinitialisée avec succès.',
            'details' => $output,
        ]);
    }

    if ($action === 'seed-only') {
        Artisan::call('db:seed', ['--class' => 'AdminSeeder', '--force' => true]);
        $output[] = Artisan::output();
        Artisan::call('db:seed', ['--class' => 'CategorySeeder', '--force' => true]);
        $output[] = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Seeders exécutés.',
            'details' => $output,
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Route de setup active.',
        'actions' => [
            'clear-and-seed' => '?action=clear-and-seed — Vide tout et crée admin + catégories',
            'seed-only'      => '?action=seed-only — Crée admin + catégories sans vider',
        ],
    ]);
});
