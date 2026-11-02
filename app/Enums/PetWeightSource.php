<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * De dónde viene un pesaje: lo medido (consulta, pesaje) se distingue de lo declarado al registrar la mascota.
 */
enum PetWeightSource: string implements HasColor, HasLabel
{
    case Registration = 'registration';
    case Consultation = 'consultation';
    case Weighing = 'weighing';
    case Edit = 'edit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Registration => 'Registro',
            self::Consultation => 'Consulta',
            self::Weighing => 'Pesaje',
            self::Edit => 'Edición',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registration => 'gray',
            self::Consultation => 'info',
            self::Weighing => 'success',
            self::Edit => 'warning',
        };
    }
}
