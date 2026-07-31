<?php

use App\Filament\Resources\UserResource;
use App\Models\User;

it('solo admin puede ver/crear/editar/eliminar usuarios', function (string $role, bool $expected) {
    $otroUsuario = User::factory()->create();
    actingAsRole($role);

    expect(UserResource::canViewAny())->toBe($expected)
        ->and(UserResource::canCreate())->toBe($expected)
        ->and(UserResource::canEdit($otroUsuario))->toBe($expected)
        ->and(UserResource::canDelete($otroUsuario))->toBe($expected);
})->with([
    ['admin', true],
    ['veterinarian', false],
    ['assistant', false],
]);
