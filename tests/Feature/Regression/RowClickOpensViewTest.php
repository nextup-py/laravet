<?php

use App\Filament\Resources\CityResource;
use App\Filament\Resources\CityResource\Pages\ListCities;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\NeighborhoodResource;
use App\Filament\Resources\NeighborhoodResource\Pages\ListNeighborhoods;
use App\Filament\Resources\OwnerResource;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\City;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Cada listado con su Resource, el permiso base de su Policy y cómo obtener un registro de ejemplo.
 *
 * @return array<string, array{0: class-string, 1: class-string, 2: string, 3: Closure(): Model}>
 */
function rowClickCases(): array
{
    return [
        'mascotas' => [ListPets::class, PetResource::class, 'pet', fn () => Pet::factory()->create()],
        'propietarios' => [ListOwners::class, OwnerResource::class, 'owner', fn () => Pet::factory()->create()->owner],
        'consultas' => [ListConsultations::class, ConsultationResource::class, 'consultation', fn () => Consultation::factory()->create()],
        'vacunaciones' => [ListVaccinations::class, VaccinationResource::class, 'vaccination', fn () => Vaccination::factory()->create()],
        'cirugías' => [ListSurgeries::class, SurgeryResource::class, 'surgery', fn () => Surgery::factory()->create()],
        'pruebas' => [ListTests::class, TestResource::class, 'test', fn () => Test::factory()->create()],
        'usuarios' => [ListUsers::class, UserResource::class, 'user', fn () => User::factory()->create()],
        'roles' => [ListRoles::class, RoleResource::class, 'role', fn () => Role::findByName('veterinarian')],
        'departamentos' => [ListDepartments::class, DepartmentResource::class, 'department', fn () => Department::first()],
        'ciudades' => [ListCities::class, CityResource::class, 'city', fn () => City::first()],
        'barrios' => [ListNeighborhoods::class, NeighborhoodResource::class, 'neighborhood', fn () => Neighborhood::first()],
    ];
}

/**
 * Usuario autenticado con un rol propio que solo tiene los permisos indicados.
 *
 * @param  array<int, string>  $permissions
 */
function actingAsWithPermissions(array $permissions): User
{
    $role = Role::create(['name' => 'rol-de-prueba', 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

    return actingAsRole($role->name);
}

function rowUrl(string $page, Model $record): ?string
{
    return Livewire::test($page)->instance()->getTable()->getRecordUrl($record);
}

it('el clic en la fila abre la ficha, no la edición', function (string $name) {
    [$page, $resource, , $make] = rowClickCases()[$name];
    actingAsRole('admin');
    $record = $make();

    expect(rowUrl($page, $record))
        ->toBe($resource::getUrl('view', ['record' => $record]))
        ->not->toContain('/edit');
})->with(array_keys(rowClickCases()));

it('quien puede editar pero no ver el registro cae en la edición', function (string $name) {
    [$page, $resource, $base, $make] = rowClickCases()[$name];
    $record = $make();
    actingAsWithPermissions(["view_any_{$base}", "update_{$base}"]);

    expect(rowUrl($page, $record))->toBe($resource::getUrl('edit', ['record' => $record]));
})->with(array_keys(rowClickCases()));

it('sin permiso para ver ni editar la fila no es un enlace', function (string $name) {
    [$page, , $base, $make] = rowClickCases()[$name];
    $record = $make();
    actingAsWithPermissions(["view_any_{$base}"]);

    expect(rowUrl($page, $record))->toBeNull();
})->with(array_keys(rowClickCases()));

it('los resultados de la búsqueda global abren la ficha', function (string $name) {
    [, $resource, , $make] = rowClickCases()[$name];
    actingAsRole('admin');
    $record = $make();

    expect($resource::getGlobalSearchResultUrl($record))->toBe($resource::getUrl('view', ['record' => $record]));
})->with(array_keys(rowClickCases()));
