<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\City;
use App\Models\Consultation;
use App\Models\Neighborhood;
use App\Models\Pet;
use App\Models\User;
use Livewire\Livewire;

function veterinarioConUbicacion(array $atributos = []): User
{
    $city = City::query()->whereHas('neighborhoods')->firstOrFail();
    $neighborhood = Neighborhood::query()->where('city_id', $city->id)->firstOrFail();

    $user = User::factory()->create($atributos + [
        'department_id' => $city->department_id,
        'city_id' => $city->id,
        'neighborhood_id' => $neighborhood->id,
        'address' => 'Calle 1',
    ]);
    $user->assignRole('veterinarian');

    return $user;
}

it('no se puede eliminar a uno mismo', function () {
    $admin = actingAsRole('admin');
    User::factory()->create()->assignRole('admin'); // hay otro admin, el bloqueo es por ser uno mismo

    expect($admin->deletionBlockedReason($admin))->toContain('propio usuario');
});

it('no se puede eliminar al único administrador', function () {
    $actor = User::factory()->create();
    $actor->assignRole('veterinarian');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->deletionBlockedReason($actor))->toContain('único administrador');
});

it('no se puede eliminar a un usuario con registros clínicos', function () {
    $admin = actingAsRole('admin');
    $vet = veterinarioConUbicacion();
    Consultation::factory()->create(['user_id' => $vet->id]);

    expect($vet->deletionBlockedReason($admin))->toContain('registros clínicos');
});

it('se puede eliminar a un usuario sin registros que no es admin ni uno mismo', function () {
    $admin = actingAsRole('admin');
    $vet = veterinarioConUbicacion();

    expect($vet->deletionBlockedReason($admin))->toBeNull();

    Livewire::test(EditUser::class, ['record' => $vet->getRouteKey()])
        ->callAction('delete');

    expect(User::find($vet->id))->toBeNull();
});

it('la acción eliminar queda deshabilitada y no borra cuando el usuario tiene registros', function () {
    actingAsRole('admin');
    $vet = veterinarioConUbicacion();
    Pet::factory()->create(['user_id' => $vet->id]);

    Livewire::test(EditUser::class, ['record' => $vet->getRouteKey()])
        ->assertActionDisabled('delete');

    expect(User::find($vet->id))->not->toBeNull();
});

it('un admin no puede cambiar su propio rol', function () {
    $admin = actingAsRole('admin');
    User::factory()->create()->assignRole('admin');

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertFormFieldIsDisabled('role');
});

it('la regla de rol rechaza dejar al sistema sin administradores', function () {
    $unico = User::factory()->create();
    $unico->assignRole('admin');
    $admin = actingAsRole('admin');
    // Dejar a $admin como veterinario para que $unico sea el único administrador.
    $admin->syncRoles(['veterinarian']);
    $admin->givePermissionTo(['view_any_user', 'view_user', 'update_user']);

    Livewire::test(EditUser::class, ['record' => $unico->getRouteKey()])
        ->fillForm(['role' => 'veterinarian'])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($unico->fresh()->hasRole('admin'))->toBeTrue();
});

it('la contraseña pide confirmación y no cambia si se deja vacía', function () {
    actingAsRole('admin');
    $vet = veterinarioConUbicacion(['password' => bcrypt('original-123')]);
    $hash = $vet->password;

    Livewire::test(EditUser::class, ['record' => $vet->getRouteKey()])
        ->fillForm(['password' => 'nueva-clave-1', 'password_confirmation' => 'distinta-clave'])
        ->call('save')
        ->assertHasFormErrors(['password']);

    expect($vet->fresh()->password)->toBe($hash);

    Livewire::test(EditUser::class, ['record' => $vet->getRouteKey()])
        ->fillForm(['password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vet->fresh()->password)->not->toBe($hash);
});

it('el correo se guarda en minúsculas', function () {
    actingAsRole('admin');
    $city = City::query()->whereHas('neighborhoods')->firstOrFail();
    $neighborhood = Neighborhood::query()->where('city_id', $city->id)->firstOrFail();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Ana Prueba',
            'email' => '  Ana.Prueba@Ejemplo.COM ',
            'password' => 'clave-segura-1',
            'password_confirmation' => 'clave-segura-1',
            'role' => 'assistant',
            'department_id' => $city->department_id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => 'Calle 1',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('name', 'Ana Prueba')->first()->email)->toBe('ana.prueba@ejemplo.com');
});

it('la tabla muestra la actividad clínica de cada usuario', function () {
    actingAsRole('admin');
    $vet = veterinarioConUbicacion();
    Consultation::factory()->count(3)->create(['user_id' => $vet->id]);
    $otro = veterinarioConUbicacion();

    Livewire::test(ListUsers::class)
        ->sortTable('consultations_count', 'desc')
        ->assertCanSeeTableRecords([$vet, $otro], inOrder: true)
        ->assertSee('Consultas');
});

it('filtra por rol, ubicación y actividad clínica', function () {
    actingAsRole('admin');
    $activo = veterinarioConUbicacion();
    Consultation::factory()->create(['user_id' => $activo->id]);
    $inactivo = veterinarioConUbicacion();
    $inactivo->syncRoles(['assistant']);

    Livewire::test(ListUsers::class)
        ->filterTable('roles', ['assistant'])
        ->assertCanSeeTableRecords([$inactivo])
        ->assertCanNotSeeTableRecords([$activo]);

    Livewire::test(ListUsers::class)
        ->filterTable('has_activity', true)
        ->assertCanSeeTableRecords([$activo])
        ->assertCanNotSeeTableRecords([$inactivo]);

    Livewire::test(ListUsers::class)
        ->filterTable('has_activity', false)
        ->assertCanSeeTableRecords([$inactivo])
        ->assertCanNotSeeTableRecords([$activo]);

    Livewire::test(ListUsers::class)
        ->filterTable('ubicacion', ['department_id' => $activo->department_id, 'city_id' => $activo->city_id])
        ->assertCanSeeTableRecords([$activo, $inactivo]);
});

it('la búsqueda global de usuarios muestra correo y rol', function () {
    $vet = veterinarioConUbicacion();

    expect(UserResource::getGloballySearchableAttributes())->toBe(['name', 'email'])
        ->and(UserResource::getGlobalSearchResultDetails($vet->load('roles')))->toMatchArray([
            'Correo' => $vet->email,
            'Rol' => 'Veterinario',
        ]);
});

it('la ficha usa el nombre como título y muestra la actividad clínica', function () {
    actingAsRole('admin');
    $vet = veterinarioConUbicacion();
    Consultation::factory()->count(2)->create(['user_id' => $vet->id]);

    Livewire::test(ViewUser::class, ['record' => $vet->getRouteKey()])
        ->assertSee($vet->name)
        ->assertSee('Actividad clínica')
        ->assertSee('Consultas');
});
