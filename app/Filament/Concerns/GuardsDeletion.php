<?php

namespace App\Filament\Concerns;

use Closure;
use Filament\Actions\MountableAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloquea una acción de eliminar cuando el registro está en uso.
 *
 * Los registros geográficos (departamentos, ciudades, barrios) están referenciados con
 * `cascadeOnDelete` por propietarios y usuarios: eliminar uno en uso borraría a las personas
 * que viven ahí y, en cascada, su historial clínico. El modelo expone `deletionBlockedReason()`;
 * para modelos de terceros (ej. el Role de Spatie) se pasa un `$resolver` que recibe el registro.
 */
final class GuardsDeletion
{
    public static function apply(MountableAction $action, ?Closure $resolver = null): MountableAction
    {
        $resolver ??= fn (Model $record): ?string => $record->deletionBlockedReason();

        return $action
            ->disabled(fn (Model $record): bool => filled($resolver($record)))
            ->tooltip(fn (Model $record): ?string => $resolver($record))
            ->before(function (MountableAction $action, Model $record) use ($resolver): void {
                if ($reason = $resolver($record)) {
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
