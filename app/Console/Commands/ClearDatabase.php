<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDatabase extends Command
{
    protected $signature = 'db:clear';
    protected $description = 'Vider toutes les tables de la base de données.';

    public function handle()
    {
        // Désactiver les clés étrangères
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Obtenez tous les noms des tables
        $tables = DB::select('SHOW TABLES');
        $tables = array_map('current', $tables);

        // Vider chaque table
        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->info("Table {$table} vidée avec succès.");
        }

        // Réactiver les clés étrangères
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Toutes les tables ont été vidées avec succès.');
    }
}