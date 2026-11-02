<?php

use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Models\Pet;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('«Registrar peso» desde la fila agrega un pesaje y actualiza el peso actual', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['species' => PetSpecies::Canino, 'size' => PetSize::Medium, 'weight' => 18]);

    Livewire::test(ListPets::class)
        ->callTableAction('registerWeight', $pet, ['weighed_at' => today()->toDateString(), 'weight' => 19.5])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Peso registrado');

    expect((float) $pet->fresh()->weight)->toBe(19.5)
        ->and($pet->fresh()->size)->toBe(PetSize::Medium)
        ->and($pet->weights()->count())->toBe(2);
});

it('el tamaño solo cambia si se confirma con el interruptor', function () {
    actingAsRole('admin');
    $sinConfirmar = Pet::factory()->create(['species' => PetSpecies::Canino, 'size' => PetSize::Medium, 'weight' => 18]);
    $confirmado = Pet::factory()->create(['species' => PetSpecies::Canino, 'size' => PetSize::Medium, 'weight' => 18]);

    Livewire::test(ViewPet::class, ['record' => $sinConfirmar->id])
        ->callAction('registerWeight', ['weighed_at' => today()->toDateString(), 'weight' => 40]);
    Livewire::test(ViewPet::class, ['record' => $confirmado->id])
        ->callAction('registerWeight', ['weighed_at' => today()->toDateString(), 'weight' => 40, 'update_size' => true]);

    expect($sinConfirmar->fresh()->size)->toBe(PetSize::Medium)
        ->and((float) $sinConfirmar->fresh()->weight)->toBe(40.0)
        ->and($confirmado->fresh()->size)->toBe(PetSize::Big);
});

it('un pesaje con fecha anterior al último no cambia el peso actual', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['weight' => 20]);

    Livewire::test(ViewPet::class, ['record' => $pet->id])
        ->callAction('registerWeight', ['weighed_at' => today()->subMonth()->toDateString(), 'weight' => 15]);

    expect((float) $pet->fresh()->weight)->toBe(20.0)
        ->and($pet->weights()->count())->toBe(2);
});

it('la acción no se ofrece en mascotas inactivas ni sin permiso de edición', function () {
    actingAsRole('admin');
    $inactiva = Pet::factory()->inactiva()->create();

    Livewire::test(ListPets::class)->assertTableActionHidden('registerWeight', $inactiva);

    $viewer = User::factory()->create();
    $role = Role::create(['name' => 'solo-lectura']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $viewer->assignRole($role);
    $this->actingAs($viewer);
    $activa = Pet::factory()->create();

    Livewire::test(ListPets::class)->assertTableActionHidden('registerWeight', $activa);
});

it('rechaza pesos fuera de rango y fechas futuras', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ViewPet::class, ['record' => $pet->id])
        ->callAction('registerWeight', ['weighed_at' => today()->addDay()->toDateString(), 'weight' => 500])
        ->assertHasActionErrors(['weighed_at', 'weight']);
});
