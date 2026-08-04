<?php

use App\Filament\Resources\ConsultationResource\Pages\ViewConsultation;
use App\Models\Consultation;
use Livewire\Livewire;

it('renderiza ViewConsultation sin datos de IA', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create(['ai_suggested_at' => null]);

    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSuccessful();
});

it('renderiza ViewConsultation con datos de IA y urgencia', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create([
        'diagnosis' => 'X',
        'treatment' => 'Y',
        'ai_diagnosis_suggestion' => 'X',
        'ai_treatment_suggestion' => 'Y',
        'ai_urgency' => 'emergencia',
        'ai_suggested_at' => now(),
        'ai_input_tokens' => 10,
        'ai_output_tokens' => 20,
    ]);

    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSuccessful();
});
