<?php

namespace App\Observers;

use App\Models\Consultation;
use App\Models\PetWeight;

/**
 * El peso anotado en una consulta es un pesaje de la mascota con la fecha de la consulta.
 * Si la consulta se edita, se borra o pierde el peso, el pesaje la acompaña.
 */
class ConsultationObserver
{
    public function saved(Consultation $consultation): void
    {
        if (blank($consultation->weight)) {
            $this->forgetWeight($consultation);

            return;
        }

        PetWeight::updateOrCreate(
            ['consultation_id' => $consultation->id],
            [
                'pet_id' => $consultation->pet_id,
                'weight' => $consultation->weight,
                'weighed_at' => ($consultation->consultation_date ?? $consultation->created_at ?? today())->toDateString(),
                'user_id' => $consultation->user_id,
            ],
        );
    }

    public function deleted(Consultation $consultation): void
    {
        $this->forgetWeight($consultation);
    }

    private function forgetWeight(Consultation $consultation): void
    {
        PetWeight::query()->where('consultation_id', $consultation->id)->get()->each->delete();
    }
}
