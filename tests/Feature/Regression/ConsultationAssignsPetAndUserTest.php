<?php

use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Models\Pet;
use Livewire\Livewire;

it('crear una consulta desde el listado top-level asigna pet_id y user_id', function () {
    $vet = actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'consultation_date' => now()->format('Y-m-d'),
            'anamnesis' => 'Anamnesis de prueba',
            'diagnosis' => 'Diagnóstico de prueba',
            'treatment' => 'Tratamiento de prueba',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    test()->assertDatabaseHas('consultations', [
        'pet_id' => $pet->id,
        'user_id' => $vet->id,
        'diagnosis' => 'Diagnóstico de prueba',
    ]);
});
