<?php

use App\Settings\ClinicSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Idempotente: el deploy corre las migraciones sin seeders y una propiedad
     * ya existente no se vuelve a agregar.
     */
    public function up(): void
    {
        $defaults = [
            'short_name' => null,
            'timezone' => config('app.timezone'),
            'reminders_enabled' => true,
            'reminder_days_before' => 3,
            'reminder_days_overdue' => 30,
            'reminder_time' => '00:00',
            'reminder_email_message' => null,
            'reminder_whatsapp_message' => null,
            'pdf_footer' => null,
            'pdf_legal_text' => null,
            'pdf_signature' => null,
            'vaccines' => ClinicSettings::DEFAULT_VACCINES,
            'manufacturers' => ClinicSettings::DEFAULT_MANUFACTURERS,
            'surgery_types' => ClinicSettings::DEFAULT_SURGERY_TYPES,
            'test_types' => ClinicSettings::DEFAULT_TEST_TYPES,
        ];

        foreach ($defaults as $property => $value) {
            if (! $this->migrator->exists("clinic.{$property}")) {
                $this->migrator->add("clinic.{$property}", $value);
            }
        }
    }
};
