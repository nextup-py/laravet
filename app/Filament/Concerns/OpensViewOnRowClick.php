<?php

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Para las páginas de listado: el clic en una fila abre la ficha del registro (ver `OpensViewFirst`).
 * Filament solo mira las acciones de tabla `view` y `edit`; como el menú de la fila no tiene «Ver»,
 * sin esto siempre abriría la edición.
 */
trait OpensViewOnRowClick
{
    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Model $record): ?string => static::getResource()::viewOrEditUrl($record);
    }
}
