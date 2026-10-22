<?php

use App\Enums\OwnerGender;
use App\Filament\Resources\OwnerResource\Pages\CreateOwner;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\OwnerResource\Pages\ViewOwner;
use App\Filament\Resources\PetResource\Pages\CreatePet;
use App\Models\Owner;
use App\Models\Pet;
use Livewire\Livewire;

it('normaliza el teléfono a un enlace de WhatsApp con prefijo 595', function () {
    expect((new Owner(['phone' => '0981123456']))->whatsapp_url)->toBe('https://wa.me/595981123456')
        ->and((new Owner(['phone' => '+595981123456']))->whatsapp_url)->toBe('https://wa.me/595981123456');
});

it('castea el género del propietario al enum con etiqueta en español', function () {
    $owner = Owner::factory()->create(['gender' => 'Female']);

    expect($owner->fresh()->gender)->toBe(OwnerGender::Female)
        ->and($owner->gender->getLabel())->toBe('Femenino');
});

it('rechaza teléfonos con formato inválido al crear un propietario', function () {
    actingAsRole('admin');

    Livewire::test(CreateOwner::class)
        ->fillForm(['phone' => '123'])
        ->call('create')
        ->assertHasFormErrors(['phone' => 'regex']);
});

it('filtra propietarios con y sin mascotas', function () {
    actingAsRole('admin');
    $con = Owner::factory()->create();
    Pet::factory()->create(['owner_id' => $con->id]);
    $sin = Owner::factory()->create();

    Livewire::test(ListOwners::class)
        ->filterTable('has_pets', true)
        ->assertCanSeeTableRecords([$con])
        ->assertCanNotSeeTableRecords([$sin])
        ->filterTable('has_pets', false)
        ->assertCanSeeTableRecords([$sin])
        ->assertCanNotSeeTableRecords([$con]);
});

it('filtra propietarios por género y por ciudad', function () {
    actingAsRole('admin');
    $mujer = Owner::factory()->create(['gender' => 'Female']);
    $hombre = Owner::factory()->create(['gender' => 'Male', 'city_id' => $mujer->city_id]);

    Livewire::test(ListOwners::class)
        ->filterTable('gender', 'Female')
        ->assertCanSeeTableRecords([$mujer])
        ->assertCanNotSeeTableRecords([$hombre]);

    Livewire::test(ListOwners::class)
        ->filterTable('ubicacion', ['city_id' => $mujer->city_id, 'neighborhood_id' => null])
        ->assertCanSeeTableRecords([$mujer, $hombre]);
});

it('busca por nombre completo y CI en la tabla', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();
    $otro = Owner::factory()->create();

    Livewire::test(ListOwners::class)
        ->searchTable((string) $owner->ci)
        ->assertCanSeeTableRecords([$owner])
        ->assertCanNotSeeTableRecords([$otro]);
});

it('la ficha del propietario usa su nombre como título y lista sus mascotas', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();
    $pet = Pet::factory()->create(['owner_id' => $owner->id]);

    Livewire::test(ViewOwner::class, ['record' => $owner->getRouteKey()])
        ->assertSee($owner->full_name)
        ->assertSee($pet->name)
        ->assertActionVisible('registerPet');
});

it('registrar mascota desde la ficha precarga el propietario', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();

    Livewire::withQueryParams(['owner_id' => $owner->id])
        ->test(CreatePet::class)
        ->assertFormSet(['owner_id' => (string) $owner->id]);
});
