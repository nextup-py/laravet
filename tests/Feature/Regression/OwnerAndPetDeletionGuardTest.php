<?php

use App\Filament\Resources\OwnerResource\Pages\EditOwner;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use Livewire\Livewire;

it('no se puede eliminar una mascota con historial clínico ni un propietario con mascotas', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    Consultation::factory()->create(['pet_id' => $pet->id]);

    Livewire::test(EditPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionDisabled('delete');

    Livewire::test(EditOwner::class, ['record' => $pet->owner->getRouteKey()])
        ->assertActionDisabled('delete');
});

it('se puede eliminar una mascota sin historial y un propietario sin mascotas', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    $owner = Owner::factory()->create();

    Livewire::test(EditPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionEnabled('delete')
        ->callAction('delete');

    Livewire::test(EditOwner::class, ['record' => $owner->getRouteKey()])
        ->assertActionEnabled('delete')
        ->callAction('delete');

    expect(Pet::find($pet->id))->toBeNull()
        ->and(Owner::find($owner->id))->toBeNull();
});
