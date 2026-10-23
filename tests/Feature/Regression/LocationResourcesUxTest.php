<?php

use App\Filament\Resources\CityResource;
use App\Filament\Resources\CityResource\Pages\CreateCity;
use App\Filament\Resources\CityResource\Pages\EditCity;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\CityResource\Pages\ViewCity;
use App\Filament\Resources\CityResource\RelationManagers\NeighborhoodsRelationManager;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DepartmentResource\Pages\CreateDepartment;
use App\Filament\Resources\DepartmentResource\Pages\EditDepartment;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\DepartmentResource\Pages\ViewDepartment;
use App\Filament\Resources\NeighborhoodResource;
use App\Filament\Resources\NeighborhoodResource\Pages\CreateNeighborhood;
use App\Filament\Resources\NeighborhoodResource\Pages\EditNeighborhood;
use App\Filament\Resources\NeighborhoodResource\Pages\ListNeighborhoods;
use App\Filament\Resources\NeighborhoodResource\Pages\ViewNeighborhood;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\User;
use Livewire\Livewire;

/** Un barrio sin propietarios ni usuarios, de una ciudad sin otros usos. */
function barrioLibre(): Neighborhood
{
    $department = Department::create(['name' => 'Dpto Libre '.uniqid(), 'capital' => 'Capital']);
    $city = City::create(['department_id' => $department->id, 'name' => 'Ciudad Libre', 'population' => 100]);

    return Neighborhood::create(['city_id' => $city->id, 'name' => 'Barrio Libre']);
}

it('un barrio sin uso puede eliminarse y uno con propietarios no', function () {
    actingAsRole('admin');
    $libre = barrioLibre();
    $enUso = Neighborhood::query()->whereHas('city')->first();
    Owner::factory()->create([
        'department_id' => $enUso->city->department_id,
        'city_id' => $enUso->city_id,
        'neighborhood_id' => $enUso->id,
    ]);

    expect($libre->deletionBlockedReason())->toBeNull()
        ->and($enUso->deletionBlockedReason())->toContain('propietarios o usuarios');

    Livewire::test(EditNeighborhood::class, ['record' => $enUso->getRouteKey()])
        ->assertActionDisabled('delete');
    Livewire::test(EditNeighborhood::class, ['record' => $libre->getRouteKey()])
        ->assertActionEnabled('delete');
});

it('una ciudad con barrios o con personas no puede eliminarse', function () {
    actingAsRole('admin');
    $conBarrios = City::query()->whereHas('neighborhoods')->first();
    $vacia = City::create(['department_id' => $conBarrios->department_id, 'name' => 'Ciudad Vacía', 'population' => null]);

    expect($conBarrios->deletionBlockedReason())->toContain('barrios')
        ->and($vacia->deletionBlockedReason())->toBeNull();

    User::factory()->create(['department_id' => $vacia->department_id, 'city_id' => $vacia->id]);

    expect($vacia->fresh()->deletionBlockedReason())->toContain('propietarios o usuarios');

    Livewire::test(EditCity::class, ['record' => $conBarrios->getRouteKey()])->assertActionDisabled('delete');
});

it('un departamento con ciudades o con personas no puede eliminarse', function () {
    actingAsRole('admin');
    $conCiudades = Department::query()->whereHas('cities')->first();
    $vacio = Department::create(['name' => 'Dpto Vacío '.uniqid(), 'capital' => 'X']);

    expect($conCiudades->deletionBlockedReason())->toContain('ciudades')
        ->and($vacio->deletionBlockedReason())->toBeNull();

    Livewire::test(EditDepartment::class, ['record' => $conCiudades->getRouteKey()])->assertActionDisabled('delete');
    Livewire::test(EditDepartment::class, ['record' => $vacio->getRouteKey()])->assertActionEnabled('delete');
});

it('el modal de editar barrios en la ciudad no elimina un barrio en uso', function () {
    actingAsRole('admin');
    $barrio = Neighborhood::query()->whereHas('city')->first();
    Owner::factory()->create([
        'department_id' => $barrio->city->department_id,
        'city_id' => $barrio->city_id,
        'neighborhood_id' => $barrio->id,
    ]);

    Livewire::test(NeighborhoodsRelationManager::class, ['ownerRecord' => $barrio->city, 'pageClass' => EditCity::class])
        ->callTableAction(['edit', 'delete'], $barrio);

    expect(Neighborhood::find($barrio->id))->not->toBeNull();
});

it('no permite una ciudad repetida dentro del mismo departamento, pero sí en otro', function () {
    actingAsRole('admin');
    $existente = City::query()->first();
    $otroDepartamento = Department::where('id', '!=', $existente->department_id)->first();

    Livewire::test(CreateCity::class)
        ->fillForm(['department_id' => $existente->department_id, 'name' => $existente->name])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    Livewire::test(CreateCity::class)
        ->fillForm(['department_id' => $otroDepartamento->id, 'name' => $existente->name, 'population' => null])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('no permite un barrio repetido dentro de la misma ciudad', function () {
    actingAsRole('admin');
    $existente = Neighborhood::query()->with('city')->first();

    Livewire::test(CreateNeighborhood::class)
        ->fillForm([
            'department_id' => $existente->city->department_id,
            'city_id' => $existente->city_id,
            'name' => $existente->name,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('no permite un departamento repetido', function () {
    actingAsRole('admin');
    $existente = Department::first();

    Livewire::test(CreateDepartment::class)
        ->fillForm(['name' => $existente->name, 'capital' => 'X'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('la población de una ciudad es opcional', function () {
    actingAsRole('admin');
    $department = Department::first();

    Livewire::test(CreateCity::class)
        ->fillForm(['department_id' => $department->id, 'name' => 'Ciudad Sin Población '.uniqid(), 'population' => null])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('al editar un barrio el departamento se completa desde su ciudad y no se guarda', function () {
    actingAsRole('admin');
    $barrio = Neighborhood::query()->with('city')->first();

    Livewire::test(EditNeighborhood::class, ['record' => $barrio->getRouteKey()])
        ->assertFormSet(['department_id' => $barrio->city->department_id, 'city_id' => $barrio->city_id])
        ->fillForm(['name' => 'Nombre Editado'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($barrio->fresh()->name)->toBe('Nombre Editado');
});

it('las tablas muestran los conteos y filtran por departamento, ciudad y uso', function () {
    actingAsRole('admin');
    $enUso = Neighborhood::query()->with('city')->first();
    $owner = Owner::factory()->create([
        'department_id' => $enUso->city->department_id,
        'city_id' => $enUso->city_id,
        'neighborhood_id' => $enUso->id,
    ]);
    $libre = barrioLibre();

    Livewire::test(ListNeighborhoods::class)
        ->filterTable('in_use', true)
        ->assertCanSeeTableRecords([$enUso])
        ->assertCanNotSeeTableRecords([$libre]);

    Livewire::test(ListNeighborhoods::class)
        ->filterTable('ubicacion', ['department_id' => $libre->city->department_id, 'city_id' => null])
        ->assertCanSeeTableRecords([$libre])
        ->assertCanNotSeeTableRecords([$enUso]);

    Livewire::test(ListCities::class)
        ->filterTable('department_id', $libre->city->department_id)
        ->assertCanSeeTableRecords([$libre->city])
        ->assertCanNotSeeTableRecords([$enUso->city]);

    Livewire::test(ListCities::class)
        ->filterTable('in_use', false)
        ->searchTable('Ciudad Libre')
        ->assertCanSeeTableRecords([$libre->city])
        ->assertCanNotSeeTableRecords([$enUso->city]);

    Livewire::test(ListDepartments::class)
        ->filterTable('in_use', true)
        ->assertCanSeeTableRecords([$owner->department])
        ->assertCanNotSeeTableRecords([$libre->city->department]);
});

it('las fichas de lectura muestran los datos y la jerarquía', function () {
    actingAsRole('admin');
    $barrio = Neighborhood::query()->with('city.department')->first();

    Livewire::test(ViewNeighborhood::class, ['record' => $barrio->getRouteKey()])
        ->assertSee($barrio->name)->assertSee($barrio->city->name)->assertSee($barrio->city->department->name);
    Livewire::test(ViewCity::class, ['record' => $barrio->city->getRouteKey()])
        ->assertSee($barrio->city->name)->assertSee($barrio->city->department->name);
    Livewire::test(ViewDepartment::class, ['record' => $barrio->city->department->getRouteKey()])
        ->assertSee($barrio->city->department->name)->assertSee($barrio->city->department->capital);
});

it('la búsqueda global muestra la jerarquía', function () {
    $barrio = Neighborhood::query()->with('city.department')->first();

    expect(NeighborhoodResource::getGlobalSearchResultDetails($barrio))->toBe([
        'Ciudad' => $barrio->city->name,
        'Departamento' => $barrio->city->department->name,
    ])
        ->and(CityResource::getGlobalSearchResultDetails($barrio->city))->toBe(['Departamento' => $barrio->city->department->name])
        ->and(DepartmentResource::getGlobalSearchResultDetails($barrio->city->department))->toBe(['Capital' => $barrio->city->department->capital]);
});
