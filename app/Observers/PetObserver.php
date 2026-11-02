<?php

namespace App\Observers;

use App\Models\Pet;
use App\Models\PetWeight;
use Illuminate\Support\Facades\Auth;

/**
 * Cada vez que se carga o se cambia a mano el peso de una mascota queda también en su historial de pesos.
 */
class PetObserver
{
    public function created(Pet $pet): void
    {
        $this->recordWeight($pet);
    }

    public function updated(Pet $pet): void
    {
        if ($pet->wasChanged('weight')) {
            $this->recordWeight($pet);
        }
    }

    private function recordWeight(Pet $pet): void
    {
        PetWeight::create([
            'pet_id' => $pet->id,
            'weight' => $pet->weight,
            'weighed_at' => today(),
            'user_id' => Auth::id() ?? $pet->user_id,
        ]);
    }
}
