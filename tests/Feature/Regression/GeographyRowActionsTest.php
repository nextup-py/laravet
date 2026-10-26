<?php

use App\Filament\Resources\CityResource;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\NeighborhoodResource;
use App\Filament\Resources\NeighborhoodResource\Pages\CreateNeighborhood;
use App\Filament\Resources\NeighborhoodResource\Pages\ListNeighborhoods;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Livewire\Livewire;

it('departamentos, ciudades y barrios no repiten «Ver» en el menú de fila', function (string $page) {
    actingAsRole('admin');

    Livewire::test($page)->assertTableActionDoesNotExist('view');
})->with([ListDepartments::class, ListCities::class, ListNeighborhoods::class]);

it('agregar ciudad o barrio desde la fila precarga el padre', function () {
    actingAsRole('admin');
    $department = Department::query()->firstOrFail();
    $city = City::query()->firstOrFail();

    Livewire::test(ListDepartments::class)
        ->assertTableActionHasUrl('addCity', CityResource::getUrl('create', ['department_id' => $department->id]), $department);
    Livewire::test(ListCities::class)
        ->assertTableActionHasUrl('addNeighborhood', NeighborhoodResource::getUrl('create', ['city_id' => $city->id]), $city);

    Livewire::withQueryParams(['city_id' => $city->id])
        ->test(CreateNeighborhood::class)
        ->assertFormSet(['city_id' => (string) $city->id, 'department_id' => $city->department_id]);
});

it('el barrio solo tiene Editar en la fila', function () {
    actingAsRole('admin');
    $neighborhood = Neighborhood::query()->firstOrFail();

    Livewire::test(ListNeighborhoods::class)
        ->assertTableActionExists('edit')
        ->assertTableActionEnabled('edit', $neighborhood);
});
