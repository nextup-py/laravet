<?php

namespace App\Observers;

use App\Models\Surgery;
use App\Support\PetReproductionSync;

class SurgeryObserver
{
    /**
     * Solo al crear o al cambiar el tipo o la fecha: editar otra cosa de la cirugía (una observación) no debe
     * deshacer una corrección manual del estado reproductivo.
     */
    public function saved(Surgery $surgery): void
    {
        if ($surgery->wasRecentlyCreated || $surgery->wasChanged(['type', 'date'])) {
            PetReproductionSync::apply($surgery);
        }
    }
}
