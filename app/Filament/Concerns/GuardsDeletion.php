<?php

namespace App\Filament\Concerns;

use Filament\Actions\MountableAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloquea una acción de eliminar cuando el registro está en uso.
 *
 * Los registros geográficos (departamentos, ciudades, barrios) están referenciados con
 * `cascadeOnDelete` por propietarios y usuarios: eliminar uno en uso borraría a las personas
 * que viven ahí y, en cascada, su historial clínico. El modelo expone `deletionBlockedReason()`.
 */
final class GuardsDeletion
{
    public static function apply(MountableAction $action): MountableAction
    {
        return $action
            ->disabled(fn (Model $record): bool => filled($record->deletionBlockedReason()))
            ->tooltip(fn (Model $record): ?string => $record->deletionBlockedReason())
            ->before(function (MountableAction $action, Model $record): void {
                if ($reason = $record->deletionBlockedReason()) {
                    Notification::make()
                        ->title('No se puede eliminar')
                        ->body($reason)
                        ->danger()
                        ->send();

                    $action->cancel();
                }
            });
    }
}
