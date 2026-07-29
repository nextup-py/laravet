<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PetSize: string implements HasColor, HasIcon, HasLabel
{
    case Giant = 'Giant';
    case Big = 'Big';
    case Medium = 'Medium';
    case Small = 'Small';
    case Tiny = 'Tiny';

    public function getLabel(): string
    {
        return match ($this) {
            self::Giant => __('Giant'),
            self::Big => __('Big'),
            self::Medium => __('Medium'),
            self::Small => __('Small'),
            self::Tiny => __('Tiny'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Giant => 'danger',
            self::Big => 'warning',
            self::Medium => 'info',
            self::Small => 'success',
            self::Tiny => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return null;
    }
}
