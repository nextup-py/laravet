<?php

use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\NeighborhoodResource\Pages\ListNeighborhoods;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use Livewire\Livewire;

it('los barrios salen ordenados por ciudad y luego por nombre', function () {
    actingAsRole('admin');
    $dpto = Department::create(['name' => 'Dpto Orden', 'capital' => 'Capital']);
    $zeta = City::create(['department_id' => $dpto->id, 'name' => 'Zeta', 'population' => 1]);
    $alfa = City::create(['department_id' => $dpto->id, 'name' => 'Alfa', 'population' => 1]);
    $zetaCentro = Neighborhood::create(['city_id' => $zeta->id, 'name' => 'Orden Centro']);
    $alfaSur = Neighborhood::create(['city_id' => $alfa->id, 'name' => 'Orden Sur']);
    $alfaCentro = Neighborhood::create(['city_id' => $alfa->id, 'name' => 'Orden Centro']);

    Livewire::test(ListNeighborhoods::class)
        ->searchTable('Orden ')
        ->assertCanSeeTableRecords([$alfaCentro, $alfaSur, $zetaCentro], inOrder: true);
});

it('los barrios se pueden ordenar por departamento', function () {
    actingAsRole('admin');

    Livewire::test(ListNeighborhoods::class)
        ->sortTable('city.department.name')
        ->assertSuccessful()
        ->sortTable('city.department.name', 'desc')
        ->assertSuccessful();
});

it('los contadores de uso de los catálogos y los clínicos de usuarios vienen ocultos', function (string $list, array $columns) {
    actingAsRole('admin');

    foreach ($columns as $column) {
        Livewire::test($list)->assertTableColumnExists($column)->assertTableColumnVisible($column, false);
    }
})->with([
    'departamentos' => [ListDepartments::class, ['owners_count', 'users_count']],
    'ciudades' => [ListCities::class, ['owners_count', 'users_count']],
    'barrios' => [ListNeighborhoods::class, ['owners_count', 'users_count']],
    'usuarios' => [ListUsers::class, ['consultations_count', 'surgeries_count', 'vaccinations_count', 'tests_count']],
]);

it('la lista de usuarios muestra el teléfono y permite buscar por él', function () {
    $admin = actingAsRole('admin');
    $admin->update(['phone' => '0981555111']);
    $otro = User::factory()->create(['phone' => '0991222333']);
    $otro->assignRole('assistant');

    Livewire::test(ListUsers::class)
        ->assertTableColumnVisible('phone')
        ->searchTable('0991222333')
        ->assertCanSeeTableRecords([$otro])
        ->assertCanNotSeeTableRecords([$admin]);
});
