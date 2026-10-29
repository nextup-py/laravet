<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipo de un reporte de «Ayuda → Reportar un problema».
 */
enum ProblemReportType: string implements HasColor, HasLabel
{
    case Error = 'error';
    case Question = 'question';
    case Suggestion = 'suggestion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Error => 'Algo falla',
            self::Question => 'Tengo una duda',
            self::Suggestion => 'Sugerencia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Error => 'danger',
            self::Question => 'info',
            self::Suggestion => 'success',
        };
    }
}
