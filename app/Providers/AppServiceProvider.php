<?php

namespace App\Providers;

use App\Settings\ClinicSettings;
use App\Support\SeedReport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SeedReport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La zona horaria de la clínica (configurable) rige las fechas de toda la app.
        $timezone = ClinicSettings::tryGet()?->timezone;

        if ($timezone && $timezone !== config('app.timezone')) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
