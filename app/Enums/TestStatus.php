<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de una prueba laboratorial según tenga o no archivos de resultado cargados.
 */
enum TestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case WithResult = 'with_result';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::WithResult => 'Con resultado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::WithResult => 'success',
        };
    }
}
