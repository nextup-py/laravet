<?php

namespace App\Support;

use App\Enums\PetReproductionStatus;
use App\Models\Surgery;
use Carbon\Carbon;
use Filament\Notifications\Notification;

/**
 * Una cirugía de castración o esterilización ya realizada cambia el estado reproductivo de la mascota.
 * Solo pasa de «Normal» a «Castrado/Esterilizado»: lo que alguien corrigió a mano no se vuelve a tocar.
 */
class PetReproductionSync
{
    /** El estado que corresponde a un tipo de cirugía, o null si no afecta la reproducción. */
    public static function statusForType(?string $type): ?PetReproductionStatus
    {
        $type = mb_strtolower((string) $type);

        return match (true) {
            str_contains($type, 'castraci') => PetReproductionStatus::Castrated,
            str_contains($type, 'esteriliz'), str_contains($type, 'ovariohister'), str_contains($type, 'ovariectom') => PetReproductionStatus::Sterilized,
            default => null,
        };
    }

    /**
     * Aplica la cirugía a la mascota si ya se realizó (fecha de hoy o anterior). Devuelve true si cambió algo.
     * Usa la mascota cargada en la cirugía, así la página que la guardó sabe si hubo cambio.
     */
    public static function apply(Surgery $surgery): bool
    {
        $status = self::statusForType($surgery->type);
        $pet = $surgery->pet;

        if (! $status || ! $pet || $pet->reproduction !== PetReproductionStatus::Normal || Carbon::parse($surgery->date)->startOfDay()->gt(today())) {
            return false;
        }

        $pet->update(['reproduction' => $status]);

        return true;
    }

    /** Avisa en pantalla si al guardar la cirugía la mascota cambió de estado reproductivo. */
    public static function notifyIfChanged(Surgery $surgery): void
    {
        if ($surgery->pet?->wasChanged('reproduction')) {
            Notification::make()
                ->title('Estado reproductivo actualizado')
                ->body("{$surgery->pet->name} figura ahora como «{$surgery->pet->reproduction->getLabel()}». Puedes corregirlo desde Editar mascota.")
                ->success()
                ->send();
        }
    }
}
