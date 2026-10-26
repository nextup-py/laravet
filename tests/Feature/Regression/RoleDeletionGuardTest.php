<?php

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('la tabla de roles agrupa Editar y Duplicar, sin Ver (la fila ya abre la ficha), ni eliminar suelto ni masivo', function () {
    actingAsRole('admin');
    $role = Role::create(['name' => 'recepcion', 'guard_name' => 'web']);

    Livewire::test(ListRoles::class)
        ->assertTableActionDoesNotExist('view')
        ->assertTableActionExists('duplicate')
        ->assertTableActionExists('edit')
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete')
        ->assertCanSeeTableRecords([$role]);
});

it('no se puede eliminar un rol base ni un rol con usuarios asignados', function () {
    actingAsRole('admin');
    $custom = Role::create(['name' => 'recepcion', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($custom);

    Livewire::test(EditRole::class, ['record' => Role::findByName('veterinarian')->getRouteKey()])
        ->assertActionDisabled('delete');

    Livewire::test(EditRole::class, ['record' => $custom->getRouteKey()])
        ->assertActionDisabled('delete');
});

it('se puede eliminar un rol propio sin usuarios', function () {
    actingAsRole('admin');
    $custom = Role::create(['name' => 'recepcion', 'guard_name' => 'web']);

    Livewire::test(EditRole::class, ['record' => $custom->getRouteKey()])
        ->assertActionEnabled('delete')
        ->callAction('delete');

    expect(Role::find($custom->id))->toBeNull();
});
