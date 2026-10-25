<?php

use App\Filament\Resources\OwnerResource\Pages\ViewOwner;
use App\Filament\Resources\OwnerResource\RelationManagers\PetsRelationManager;
use App\Filament\Resources\PetResource;
use App\Models\Owner;
use App\Models\Pet;
use Livewire\Livewire;

it('la ficha del propietario lista sus mascotas solo en el RM, sin sección duplicada', function () {
    actingAsRole('veterinarian');
    $owner = Owner::factory()->create();
    $pet = Pet::factory()->create(['owner_id' => $owner->id]);
    $other = Pet::factory()->create();

    Livewire::test(ViewOwner::class, ['record' => $owner->getRouteKey()])
        ->assertSuccessful()
        ->assertDontSee('Mascotas asociadas');

    Livewire::test(PetsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => ViewOwner::class,
    ])
        ->assertCanSeeTableRecords([$pet])
        ->assertCanNotSeeTableRecords([$other]);
});

it('el RM de mascotas del propietario es de solo lectura y enlaza a la ficha de la mascota', function () {
    actingAsRole('veterinarian');
    $owner = Owner::factory()->create();
    $pet = Pet::factory()->create(['owner_id' => $owner->id]);

    $component = Livewire::test(PetsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => ViewOwner::class,
    ])
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('delete');

    expect($component->instance()->getTable()->getRecordUrl($pet))
        ->toBe(PetResource::getUrl('view', ['record' => $pet]));
});

it('el RM de mascotas del propietario filtra por especie', function () {
    actingAsRole('veterinarian');
    $owner = Owner::factory()->create();
    $dog = Pet::factory()->create(['owner_id' => $owner->id, 'species' => 'Canino']);
    $cat = Pet::factory()->create(['owner_id' => $owner->id, 'species' => 'Felino']);

    Livewire::test(PetsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => ViewOwner::class,
    ])
        ->filterTable('species', ['Canino'])
        ->assertCanSeeTableRecords([$dog])
        ->assertCanNotSeeTableRecords([$cat]);
});
