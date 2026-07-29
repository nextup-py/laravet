<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PetSpecies: string implements HasColor, HasIcon, HasLabel
{
    case Canino = 'Canino';
    case Felino = 'Felino';
    case Roedor = 'Roedor';
    case Ave = 'Ave';
    case Equino = 'Equino';
    case Bovino = 'Bovino';
    case Pez = 'Pez';
    case Reptil = 'Reptil';

    public function getLabel(): string
    {
        return match ($this) {
            self::Canino => __('Canine'),
            self::Felino => __('Feline'),
            self::Roedor => __('Rodent'),
            self::Ave => __('Bird'),
            self::Equino => __('Equine'),
            self::Bovino => __('Bovine'),
            self::Pez => __('Fish'),
            self::Reptil => __('Reptile'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Canino => 'info',
            self::Felino => 'warning',
            self::Roedor => 'gray',
            self::Ave => 'success',
            self::Equino => 'primary',
            self::Bovino => 'gray',
            self::Pez => 'info',
            self::Reptil => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return null;
    }
}
