<?php

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Confirma de punta a punta el objetivo de esta migración: un admin puede crear
 * un rol nuevo con un subconjunto de permisos (sin tocar código) y ese rol
 * respeta exactamente lo que se le asignó — ni más, ni menos.
 */
it('un rol custom creado por el admin respeta exactamente los permisos que se le asignaron', function () {
    $role = Role::create(['name' => 'recepcionista', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);

    $user = User::factory()->create();
    $user->assignRole('recepcionista');
    test()->actingAs($user);

    expect(PetResource::canViewAny())->toBeTrue()
        ->and(PetResource::canCreate())->toBeFalse()
        ->and(ConsultationResource::canViewAny())->toBeFalse();
});

it('un rol custom recién creado aparece en las opciones de rol de UserResource sin tocar código', function () {
    Role::create(['name' => 'recepcionista', 'guard_name' => 'web']);

    expect(UserResource::roleOptions())->toHaveKey('recepcionista', 'recepcionista');
});
