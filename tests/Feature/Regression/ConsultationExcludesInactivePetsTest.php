<?php

use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\ConsultationResource\Pages\EditConsultation;
use App\Models\Consultation;
use App\Models\Pet;
use Livewire\Livewire;

it('no ofrece mascotas inactivas al crear una consulta', function () {
    actingAsRole('veterinarian');
    $activePet = Pet::factory()->create(['active' => true]);
    $inactivePet = Pet::factory()->inactiva()->create();

    $options = Livewire::test(CreateConsultation::class)
        ->instance()
        ->form
        ->getComponent('data.pet_id')
        ->getOptions();

    expect(array_key_exists($activePet->id, $options))->toBeTrue()
        ->and(array_key_exists($inactivePet->id, $options))->toBeFalse();
});

it('mantiene visible la mascota ya asociada al editar una consulta aunque luego se haya marcado inactiva', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create(['active' => true]);
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);

    $pet->update(['active' => false]);

    $options = Livewire::test(EditConsultation::class, ['record' => $consultation->getRouteKey()])
        ->instance()
        ->form
        ->getComponent('data.pet_id')
        ->getOptions();

    expect(array_key_exists($pet->id, $options))->toBeTrue();
});
