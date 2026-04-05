<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Vérifier les vendeurs inactifs chaque jour à 8h
Schedule::command('notifications:inactive-sellers --days=30')->dailyAt('08:00');
