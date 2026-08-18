<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// La app corre internamente en UTC (config('app.timezone') sin APP_TIMEZONE en
// .env), así que sin esto ->daily() dispara a medianoche UTC, no a medianoche
// en Paraguay.
Schedule::command('send:vaccination-notifications')
    ->daily()
    ->timezone('America/Asuncion')
    ->withoutOverlapping();
