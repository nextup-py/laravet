<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Seguimiento de un reporte de problema: nuevo, visto por el administrador o resuelto.
 */
enum ProblemReportStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Seen = 'seen';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Seen => 'Visto',
            self::Resolved => 'Resuelto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Seen => 'info',
            self::Resolved => 'success',
        };
    }
}
