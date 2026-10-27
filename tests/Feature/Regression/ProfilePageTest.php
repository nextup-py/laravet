<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function profileLocation(): array
{
    $department = Department::create(['name' => 'Dpto '.uniqid(), 'capital' => 'Capital']);
    $city = City::create(['department_id' => $department->id, 'name' => 'Ciudad', 'population' => 100]);
    $neighborhood = Neighborhood::create(['city_id' => $city->id, 'name' => 'Barrio']);

    return [
        'department_id' => $department->id,
        'city_id' => $city->id,
        'neighborhood_id' => $neighborhood->id,
        'address' => 'Calle 1',
    ];
}

it('cualquier usuario con rol abre su perfil y no necesita permisos de usuarios', function () {
    actingAsRole('assistant');

    $this->get(EditProfile::getUrl())->assertOk();
});

it('el usuario edita sus datos, teléfono y domicilio desde el perfil', function () {
    $user = actingAsRole('veterinarian');

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Nuevo Nombre', 'phone' => '0981 123 456', ...profileLocation()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh())
        ->name->toBe('Nuevo Nombre')
        ->phone->toBe('0981 123 456');
});

it('sube la foto de perfil al disco público y la expone como avatar', function () {
    Storage::fake('public');
    $user = actingAsRole('veterinarian');

    Livewire::test(EditProfile::class)
        ->fillForm(['avatar_url' => UploadedFile::fake()->image('foto.png'), ...profileLocation()])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    Storage::disk('public')->assertExists($user->avatar_url);
    expect($user->getFilamentAvatarUrl())->not->toBeNull();
});

it('cambiar la contraseña exige la contraseña actual correcta', function () {
    $user = actingAsRole('veterinarian');
    $user->update(['password' => 'clave-vieja-123']);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'clave-nueva-456',
            'passwordConfirmation' => 'clave-nueva-456',
            'current_password' => 'incorrecta',
            ...profileLocation(),
        ])
        ->call('save')
        ->assertHasFormErrors(['current_password']);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'clave-nueva-456',
            'passwordConfirmation' => 'clave-nueva-456',
            'current_password' => 'clave-vieja-123',
            ...profileLocation(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('clave-nueva-456', $user->refresh()->password))->toBeTrue();
});

it('cambiar el correo exige la contraseña actual', function () {
    $user = actingAsRole('veterinarian');
    $user->update(['password' => 'clave-vieja-123']);

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'otro@example.com', ...profileLocation()])
        ->call('save')
        ->assertHasFormErrors(['current_password' => 'required']);

    expect($user->refresh()->email)->not->toBe('otro@example.com');
});

it('el perfil no permite cambiar el rol', function () {
    actingAsRole('veterinarian');

    Livewire::test(EditProfile::class)->assertFormFieldDoesNotExist('role');
});
