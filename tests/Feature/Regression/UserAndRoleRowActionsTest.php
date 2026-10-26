<?php

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\User;
use App\Models\Vaccination;
use Filament\Notifications\Auth\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('usuarios y roles no repiten «Ver» en el menú de fila', function () {
    actingAsRole('admin');

    Livewire::test(ListUsers::class)->assertTableActionDoesNotExist('view');
    Livewire::test(ListRoles::class)->assertTableActionDoesNotExist('view');
});

it('usuarios: enlace de contraseña y atajos a sus registros filtrados', function () {
    actingAsRole('admin');
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertTableActionExists('sendPasswordReset')
        ->assertTableActionHasUrl('viewConsultations', ConsultationResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]), $user);
});

it('enviar el enlace de contraseña notifica al usuario', function () {
    Notification::fake();
    actingAsRole('admin');
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('sendPasswordReset', $user)
        ->assertNotified('Enlace enviado');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('el filtro de vacunaciones por veterinario separa los registros de cada usuario', function () {
    actingAsRole('admin');
    $user = User::factory()->create();
    $suya = Vaccination::factory()->create(['user_id' => $user->id]);
    $ajena = Vaccination::factory()->create();

    Livewire::test(ListVaccinations::class)
        ->filterTable('user_id', $user->id)
        ->assertCanSeeTableRecords([$suya])
        ->assertCanNotSeeTableRecords([$ajena]);
});

it('roles: duplicar crea un rol nuevo con los mismos permisos', function () {
    actingAsRole('admin');
    $original = Role::findByName('veterinarian');

    Livewire::test(ListRoles::class)
        ->callTableAction('duplicate', $original, ['name' => 'veterinario-junior'])
        ->assertHasNoTableActionErrors();

    $copy = Role::findByName('veterinario-junior');
    expect($copy->permissions->pluck('name')->sort()->values()->all())
        ->toBe($original->permissions->pluck('name')->sort()->values()->all());
});

it('roles: no se puede duplicar con un nombre que ya existe', function () {
    actingAsRole('admin');

    Livewire::test(ListRoles::class)
        ->callTableAction('duplicate', Role::findByName('veterinarian'), ['name' => 'assistant'])
        ->assertHasTableActionErrors(['name']);
});
