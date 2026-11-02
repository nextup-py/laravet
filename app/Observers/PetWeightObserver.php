<?php

namespace App\Observers;

use App\Models\Pet;
use App\Models\PetWeight;

/**
 * Mantiene `pets.weight` igual al pesaje más reciente de la mascota.
 */
class PetWeightObserver
{
    public function saved(PetWeight $weight): void
    {
        $weight->pet?->refreshCurrentWeight();

        if ($weight->wasChanged('pet_id') && $weight->getOriginal('pet_id')) {
            Pet::find($weight->getOriginal('pet_id'))?->refreshCurrentWeight();
        }
    }

    public function deleted(PetWeight $weight): void
    {
        $weight->pet?->refreshCurrentWeight();
    }
}
