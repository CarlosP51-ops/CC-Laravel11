<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAllData extends Command
{
    protected $signature   = 'db:clear-data {--force : Skip confirmation}';
    protected $description = 'Vide toutes les tables sans les supprimer, puis crée l\'admin';

    public function handle(): void
    {
        if (!$this->option('force') && !$this->confirm('⚠️  Cela va supprimer TOUTES les données. Continuer ?')) {
            $this->info('Annulé.');
            return;
        }

        $this->info('Désactivation des contraintes FK...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key    = "Tables_in_{$dbName}";

        $skip = ['migrations', 'password_reset_tokens', 'personal_access_tokens'];

        foreach ($tables as $table) {
            $name = $table->$key;
            if (in_array($name, $skip)) continue;
            DB::table($name)->truncate();
            $this->line("  ✓ {$name} vidée");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Réactivation des contraintes FK.');
        $this->info('Création de l\'admin...');

        $this->call('db:seed', ['--class' => 'AdminSeeder']);

        $this->info('✅ Terminé.');
    }
}
