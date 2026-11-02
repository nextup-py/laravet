<?php

use App\Settings\ClinicSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Umbrales de peso por especie para proponer el tamaño de la mascota. Idempotente: el deploy no corre seeders.
     */
    public function up(): void
    {
        if (! $this->migrator->exists('clinic.size_thresholds')) {
            $this->migrator->add('clinic.size_thresholds', ClinicSettings::DEFAULT_SIZE_THRESHOLDS);
        }
    }
};
