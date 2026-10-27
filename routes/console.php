<?php

use App\Settings\ClinicSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Hora y zona horaria configurables en «Datos de la clínica → Recordatorios»;
// sin settings legibles (instalación nueva) corre a medianoche en Paraguay.
$clinic = ClinicSettings::tryGet();

Schedule::command('send:vaccination-notifications')
    ->dailyAt($clinic?->reminder_time ?? '00:00')
    ->timezone($clinic?->timezone ?? 'America/Asuncion')
    ->withoutOverlapping();

// Avisos al personal (campanita y correo según las preferencias de cada usuario),
// a primera hora en la zona horaria de la clínica.
Schedule::command('send:staff-alerts')
    ->dailyAt('07:00')
    ->timezone($clinic?->timezone ?? 'America/Asuncion')
    ->withoutOverlapping();
