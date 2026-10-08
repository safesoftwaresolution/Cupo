<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de citas por WhatsApp. Requiere `php artisan schedule:work` (o un cron) y un worker de colas.
Schedule::command('recordatorios:enviar')->everyFiveMinutes()->withoutOverlapping();
