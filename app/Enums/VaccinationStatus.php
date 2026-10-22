<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Estado de una vacunación según su fecha de próxima aplicación.
 */
enum VaccinationStatus: string implements HasColor, HasLabel
{
    case Overdue = 'overdue';
    case DueSoon = 'due_soon';
    case UpToDate = 'up_to_date';

    /** Días hacia adelante en los que una vacunación se considera "por vencer". */
    public const DUE_SOON_DAYS = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::Overdue => 'Vencida',
            self::DueSoon => 'Por vencer',
            self::UpToDate => 'Al día',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Overdue => 'danger',
            self::DueSoon => 'warning',
            self::UpToDate => 'success',
        };
    }
}
