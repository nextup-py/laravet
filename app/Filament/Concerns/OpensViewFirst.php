<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Para los Resources con página de ficha: el destino de un registro es su ficha y, si el usuario no
 * puede verla pero sí editarla, la edición. Filament elige por su cuenta la edición primero; aquí
 * se invierte el orden. El clic en la fila lo resuelve `OpensViewOnRowClick` en la página de listado.
 */
trait OpensViewFirst
{
    /**
     * URL a la que lleva un registro: su ficha, o su edición si solo puede editarlo, o null.
     */
    public static function viewOrEditUrl(Model $record): ?string
    {
        if (static::hasPage('view') && static::canView($record)) {
            return static::getUrl('view', ['record' => $record]);
        }

        if (static::hasPage('edit') && static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $record]);
        }

        return null;
    }

    /**
     * Los resultados de la búsqueda global también abren la ficha.
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::viewOrEditUrl($record);
    }
}
