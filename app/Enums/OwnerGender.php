<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OwnerGender: string implements HasColor, HasLabel
{
    case Male = 'Male';
    case Female = 'Female';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Masculino',
            self::Female => 'Femenino',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Male => 'info',
            self::Female => 'warning',
        };
    }
}
