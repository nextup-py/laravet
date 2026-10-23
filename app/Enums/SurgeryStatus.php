<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de una cirugía según su fecha: programada (futura), hoy o realizada (pasada).
 */
enum SurgeryStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Today = 'today';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::Today => 'Hoy',
            self::Done => 'Realizada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Today => 'warning',
            self::Done => 'success',
        };
    }
}
