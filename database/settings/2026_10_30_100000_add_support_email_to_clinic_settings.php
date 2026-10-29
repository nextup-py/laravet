<?php

use App\Settings\ClinicSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Correo de soporte del sistema (Ayuda → Soporte). Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        if (! $this->migrator->exists('clinic.support_email')) {
            $this->migrator->add('clinic.support_email', ClinicSettings::DEFAULT_SUPPORT_EMAIL);
        }
    }
};
