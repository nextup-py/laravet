<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AiUsageStatus: string implements HasColor, HasLabel
{
    case Manual = 'Manual';
    case UsedAsIs = 'UsedAsIs';
    case Edited = 'Edited';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::UsedAsIs => 'Usado tal cual',
            self::Edited => 'Editado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::UsedAsIs => 'info',
            self::Edited => 'success',
        };
    }
}
