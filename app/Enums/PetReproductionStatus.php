<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PetReproductionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Normal = 'Normal';
    case Castrated = 'Castrated';
    case Sterilized = 'Sterilized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Normal => __('Normal'),
            self::Castrated => __('Castrated'),
            self::Sterilized => __('Sterilized'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::Castrated => 'success',
            self::Sterilized => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return null;
    }
}
