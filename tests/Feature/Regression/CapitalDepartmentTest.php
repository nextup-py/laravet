<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\User;

function migracionCapital(): object
{
    return require database_path('migrations/2026_11_02_170000_move_asuncion_to_capital_department.php');
}

/**
 * Estado previo a la migración (una base ya en producción): sin departamento «Capital» y con Asunción
 * dentro de Central, con un propietario y un usuario que viven ahí. La base de pruebas ya trae las regiones nuevas.
 *
 * @return array{0: Department, 1: City, 2: Neighborhood, 3: Owner, 4: User}
 */
function asuncionEnCentral(): array
{
    $central = Department::where('name', 'Central')->firstOrFail();
    $asuncion = City::where('name', 'Asunción')->firstOrFail();
    $barrio = $asuncion->neighborhoods()->firstOrFail();

    $asuncion->update(['department_id' => $central->id]);
    Department::where('name', 'Capital')->delete();

    $propietario = Owner::factory()->create(['department_id' => $central->id, 'city_id' => $asuncion->id, 'neighborhood_id' => $barrio->id]);
    $usuario = User::factory()->create(['department_id' => $central->id, 'city_id' => $asuncion->id, 'neighborhood_id' => $barrio->id]);

    return [$central, $asuncion, $barrio, $propietario, $usuario];
}

it('una instalación nueva ya trae Asunción dentro del departamento Capital', function () {
    $asuncion = City::where('name', 'Asunción')->firstOrFail();

    expect($asuncion->department->name)->toBe('Capital')
        ->and($asuncion->department->capital)->toBe('Asunción')
        ->and(Department::count())->toBe(18)
        ->and($asuncion->neighborhoods()->count())->toBeGreaterThan(0);
});

it('la migración crea Capital y mueve Asunción con sus barrios, propietarios y usuarios', function () {
    [$central, $asuncion, $barrio, $propietario, $usuario] = asuncionEnCentral();

    migracionCapital()->up();

    $capital = Department::where('name', 'Capital')->firstOrFail();
    expect($capital->capital)->toBe('Asunción')
        ->and($asuncion->fresh()->department_id)->toBe($capital->id)
        ->and($barrio->fresh()->city_id)->toBe($asuncion->id)
        ->and($propietario->fresh()->department_id)->toBe($capital->id)
        ->and($usuario->fresh()->department_id)->toBe($capital->id)
        ->and($central->cities()->where('name', 'Asunción')->exists())->toBeFalse();
});

it('la migración es idempotente', function () {
    asuncionEnCentral();

    migracionCapital()->up();
    migracionCapital()->up();

    expect(Department::where('name', 'Capital')->count())->toBe(1);
});

it('si Asunción ya está en Capital la migración no cambia nada', function () {
    $asuncion = City::where('name', 'Asunción')->firstOrFail();
    $departamentos = Department::count();

    migracionCapital()->up();

    expect(Department::count())->toBe($departamentos)
        ->and($asuncion->fresh()->department->name)->toBe('Capital');
});

it('el rollback devuelve Asunción a Central y quita Capital si queda vacío', function () {
    [$central, $asuncion, , $propietario] = asuncionEnCentral();

    migracionCapital()->up();
    migracionCapital()->down();

    expect($asuncion->fresh()->department_id)->toBe($central->id)
        ->and($propietario->fresh()->department_id)->toBe($central->id)
        ->and(Department::where('name', 'Capital')->exists())->toBeFalse();
});
