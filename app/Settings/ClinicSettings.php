<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ClinicSettings extends Settings
{
    public string $name;

    public ?string $logo;

    public ?string $favicon;

    public ?string $primary_color;

    public ?string $address;

    public ?string $phone;

    public ?string $email;

    public ?string $business_hours;

    public ?string $ruc;

    public ?string $razon_social;

    public ?string $facebook;

    public ?string $instagram;

    public ?string $website;

    public static function group(): string
    {
        return 'clinic';
    }
}
