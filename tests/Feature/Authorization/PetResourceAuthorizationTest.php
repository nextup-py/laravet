<?php

use App\Filament\Resources\PetResource;
use App\Models\Pet;
use App\Models\User;

it('permite ver el listado a admin, veterinario y asistente', function (string $role) {
    actingAsRole($role);

    expect(PetResource::canViewAny())->toBeTrue();
})->with(['admin', 'veterinarian', 'assistant']);

it('un usuario sin ningún rol clínico no puede ver el listado', function () {
    $user = User::factory()->create();
    test()->actingAs($user);

    expect(PetResource::canViewAny())->toBeFalse();
});

it('admin, veterinario y asistente pueden crear y editar mascotas', function (string $role) {
    $pet = Pet::factory()->create();
    actingAsRole($role);

    expect(PetResource::canCreate())->toBeTrue()
        ->and(PetResource::canEdit($pet))->toBeTrue();
})->with(['admin', 'veterinarian', 'assistant']);

it('solo admin y veterinario pueden eliminar mascotas', function (string $role, bool $puedeEliminar) {
    $pet = Pet::factory()->create();
    actingAsRole($role);

    expect(PetResource::canDelete($pet))->toBe($puedeEliminar);
})->with([
    ['admin', true],
    ['veterinarian', true],
    ['assistant', false],
]);
