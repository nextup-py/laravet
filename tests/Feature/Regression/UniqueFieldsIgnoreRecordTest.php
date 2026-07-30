<?php

use App\Filament\Resources\OwnerResource\Pages\EditOwner;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\User;
use Livewire\Livewire;

it('editar un propietario sin cambiar ci ni email no falla por unique()', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();

    Livewire::test(EditOwner::class, ['record' => $owner->getRouteKey()])
        ->fillForm([
            'ci' => $owner->ci,
            'first_name' => 'Nombre Editado',
            'last_name' => $owner->last_name,
            'gender' => $owner->gender,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'department_id' => $owner->department_id,
            'city_id' => $owner->city_id,
            'neighborhood_id' => $owner->neighborhood_id,
            'address' => $owner->address,
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('editar un usuario sin cambiar el email no falla por unique()', function () {
    actingAsRole('admin');

    $city = City::query()->whereHas('neighborhoods')->firstOrFail();
    $neighborhood = Neighborhood::query()->where('city_id', $city->id)->firstOrFail();

    $user = User::factory()->create([
        'department_id' => $city->department_id,
        'city_id' => $city->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
    $user->assignRole('veterinarian');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'name' => 'Nombre Editado',
            'email' => $user->email,
            'password' => null,
            'role' => 'veterinarian',
            'department_id' => $city->department_id,
            'city_id' => $city->id,
            'neighborhood_id' => $neighborhood->id,
            'address' => $user->address ?? 'Dirección editada',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});
