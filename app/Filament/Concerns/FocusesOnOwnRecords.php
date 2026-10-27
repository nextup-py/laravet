<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Foco por rol del dashboard: el veterinario ve sus propios registros
 * (consultas, cirugías y pruebas a su nombre); el asistente y el admin ven
 * los de toda la clínica. Los permisos de Shield siguen mandando: esto solo
 * acota qué filas muestra un widget que el usuario ya puede ver.
 */
trait FocusesOnOwnRecords
{
    protected static function focusesOnOwnRecords(): bool
    {
        return auth()->user()?->seesOnlyOwnRecords() ?? false;
    }

    protected static function scopeToOwnRecords(Builder $query): Builder
    {
        return static::focusesOnOwnRecords()
            ? $query->where('user_id', auth()->id())
            : $query;
    }

    protected static function ownershipPrefix(string $plural): string
    {
        return static::focusesOnOwnRecords() ? "Mis {$plural}" : ucfirst($plural);
    }
}
