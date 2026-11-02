<?php

namespace App\Support;

use App\Enums\PetSize;
use App\Models\Pet;
use App\Settings\ClinicSettings;
use Filament\Notifications\Notification;

/**
 * Propone el tamaño de una mascota según su peso y los umbrales de su especie («Datos de la clínica → Listas»).
 * Solo propone: cambiar el tamaño siempre lo confirma una persona.
 */
class PetSizeSuggestion
{
    public static function for(Pet $pet, float|int|string|null $weight = null): ?PetSize
    {
        $weight = (float) ($weight ?? $pet->weight);
        $thresholds = ClinicSettings::tryGet()?->size_thresholds ?? ClinicSettings::DEFAULT_SIZE_THRESHOLDS;
        $row = collect($thresholds)->firstWhere('species', $pet->species->value);

        if (! $row || $weight <= 0) {
            return null;
        }

        return match (true) {
            $weight <= (float) $row['tiny'] => PetSize::Tiny,
            $weight <= (float) $row['small'] => PetSize::Small,
            $weight <= (float) $row['medium'] => PetSize::Medium,
            $weight <= (float) $row['big'] => PetSize::Big,
            default => PetSize::Giant,
        };
    }

    /**
     * Avisa (sin cambiar nada) cuando el peso actual de la mascota sugiere otro tamaño. Se llama después de
     * guardar una consulta con peso, que no tiene dónde preguntar.
     */
    public static function notifyIfDiffers(Pet $pet): void
    {
        $pet->refresh();

        if ($suggested = self::differingFor($pet)) {
            Notification::make()
                ->title("Revisa el tamaño de {$pet->name}")
                ->body("Con {$pet->weight} kg se propone «{$suggested->getLabel()}» y hoy figura «{$pet->size->getLabel()}». Puedes cambiarlo desde Editar mascota.")
                ->warning()
                ->send();
        }
    }

    /** El tamaño propuesto, solo si difiere del actual de la mascota. */
    public static function differingFor(Pet $pet, float|int|string|null $weight = null): ?PetSize
    {
        $suggested = self::for($pet, $weight);

        return $suggested && $suggested !== $pet->size ? $suggested : null;
    }
}
