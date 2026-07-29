<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PetGender: string implements HasColor, HasIcon, HasLabel
{
    case Male = 'Male';
    case Female = 'Female';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Macho',
            self::Female => 'Hembra',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Male => 'info',
            self::Female => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return null;
    }
}
