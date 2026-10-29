<?php

use App\Settings\ClinicSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Pasa el recordatorio por correo al texto nuevo solo si la clínica seguía con el original
     * (o sin texto propio). Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        if (! $this->migrator->exists('clinic.reminder_email_message')) {
            return;
        }

        $this->migrator->update('clinic.reminder_email_message', fn (?string $message): ?string => blank($message) || $message === ClinicSettings::PREVIOUS_REMINDER_EMAIL
            ? ClinicSettings::DEFAULT_REMINDER_EMAIL
            : $message);
    }
};
