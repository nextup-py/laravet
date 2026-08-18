<?php

use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\RelationManagers\NeighborhoodsRelationManager;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Models\City;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\Pet;
use Livewire\Livewire;

/**
 * "Eliminar" deja de ser una acción de fila suelta en las tablas del panel —
 * ahora solo se puede eliminar desde adentro del modal de "Editar" (para
 * RelationManagers, vía extraModalFooterActions) o desde la página de View/Edit
 * de los Resources con página propia. Este test cubre el caso RelationManager,
 * el más propenso a romperse silenciosamente porque no tiene una página propia
 * a la que reubicar la acción.
 */
it('NeighborhoodsRelationManager ya no tiene "eliminar" como acción de fila, pero sigue disponible dentro del modal de editar', function () {
    actingAsRole('admin');
    $city = City::query()->whereHas('neighborhoods')->firstOrFail();
    $neighborhood = Neighborhood::query()->where('city_id', $city->id)->firstOrFail();

    $component = Livewire::test(NeighborhoodsRelationManager::class, [
        'ownerRecord' => $city,
        'pageClass' => EditCity::class,
    ]);

    $component->assertTableActionDoesNotExist('delete');

    $component->callTableAction(['edit', 'delete'], $neighborhood);

    expect(Neighborhood::query()->find($neighborhood->id))->toBeNull();
});

it('PetResource ya no tiene "eliminar" ni bulk-delete en la tabla, pero sigue disponible en la página Edit', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::test(EditPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionExists('delete');
});

it('ConsultationsRelationManager (dentro de la ficha de mascota) reubica eliminar dentro del modal de editar', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);

    $component = Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => EditPet::class,
    ]);

    $component->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');

    $component->callTableAction(['edit', 'delete'], $consultation);

    expect(Consultation::query()->find($consultation->id))->toBeNull();
});
