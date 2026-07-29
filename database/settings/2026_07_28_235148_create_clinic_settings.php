<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('clinic.name', config('app.name'));
        $this->migrator->add('clinic.logo', null);
        $this->migrator->add('clinic.favicon', null);
        $this->migrator->add('clinic.primary_color', null);
        $this->migrator->add('clinic.address', null);
        $this->migrator->add('clinic.phone', null);
        $this->migrator->add('clinic.email', null);
        $this->migrator->add('clinic.business_hours', null);
        $this->migrator->add('clinic.ruc', null);
        $this->migrator->add('clinic.razon_social', null);
        $this->migrator->add('clinic.facebook', null);
        $this->migrator->add('clinic.instagram', null);
        $this->migrator->add('clinic.website', null);
    }
};
