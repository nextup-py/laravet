<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Dirección de envío de los correos del sistema; arranca con la de MAIL_FROM_ADDRESS para
     * que nada cambie hasta que la clínica la edite. Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        if (! $this->migrator->exists('clinic.mail_from_address')) {
            $this->migrator->add('clinic.mail_from_address', (string) config('mail.from.address'));
        }
    }
};
