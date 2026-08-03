<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Nivel de urgencia clínica que la IA detecta a partir de la anamnesis. Los backing
 * values están en español (no en inglés, a diferencia de otros enums del proyecto)
 * porque deben coincidir exactamente con lo que el prompt le pide a la IA en su
 * respuesta JSON.
 */
enum AiUrgencyLevel: string implements HasColor, HasLabel
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Emergencia = 'emergencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Emergencia => 'Emergencia',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Baja => 'success',
            self::Media => 'warning',
            self::Alta, self::Emergencia => 'danger',
        };
    }
}
