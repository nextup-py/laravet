<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\User;
use Livewire\Livewire;

it('editar un usuario sin tocar el password no lo sobreescribe con hash de cadena vacía', function () {
    actingAsRole('admin');

    $city = City::query()->whereHas('neighborhoods')->firstOrFail();
    $neighborhood = Neighborhood::query()->where('city_id', $city->id)->firstOrFail();

    $user = User::factory()->create([
        'password' => bcrypt('secreto-original'),
        'department_id' => $city->department_id,
        'city_id' => $city->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
    $user->assignRole('veterinarian');
    $hashOriginal = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'Nombre Editado',
            'email' => $user->email,
            'password' => null,
            'role' => 'veterinarian',
            'department_id' => $city->department_id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => 'Dirección editada',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->password)->toBe($hashOriginal);
});
