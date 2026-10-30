<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\OwnerSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('test-results');
});

it('--demo siembra el demo y muestra la tabla de usuarios', function () {
    $this->artisan('laravet:seed', ['--demo' => true])
        ->expectsOutputToContain('Usuarios para entrar al panel')
        ->expectsOutputToContain('maria.gonzalez@mbopivet.com.py')
        ->expectsOutputToContain('laura.benitez@mbopivet.com.py')
        ->assertSuccessful();

    expect(Owner::count())->toBe(count(OwnerSeeder::OWNERS));
});

it('--volume agrega mascotas hasta la cantidad pedida y siembra el demo si falta', function () {
    $this->artisan('laravet:seed', ['--volume' => '4'])->assertSuccessful();

    expect(Pet::count())->toBe(15 + 4);

    $this->artisan('laravet:seed', ['--volume' => '4'])->assertSuccessful();

    expect(Pet::count())->toBe(15 + 4);
});

it('--volume rechaza valores que no son un entero positivo', function () {
    $this->artisan('laravet:seed', ['--volume' => 'mucho'])->assertFailed();
    $this->artisan('laravet:seed', ['--volume' => '0'])->assertFailed();

    expect(Pet::count())->toBe(0);
});

it('no siembra ni borra datos demo en producción', function (string $option) {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('laravet:seed', [$option => true, '--force' => true])
        ->expectsOutputToContain('no se pueden sembrar ni borrar en producción')
        ->assertFailed();

    expect(Owner::count())->toBe(0);
})->with(['--demo', '--reset-demo']);

it('--reset-demo sin --force no borra nada si no hay interacción', function () {
    $this->seed(DemoSeeder::class);

    $this->artisan('laravet:seed', ['--reset-demo' => true, '--no-interaction' => true])->assertFailed();

    expect(Owner::count())->toBe(count(OwnerSeeder::OWNERS));
});

it('--reset-demo --force borra el demo y conserva los datos de la clínica', function () {
    $this->seed(DemoSeeder::class);

    $real = Owner::factory()->create();
    $realVet = User::factory()->create();
    Pet::factory()->for($real)->for($realVet)->create();

    $this->artisan('laravet:seed', ['--reset-demo' => true, '--force' => true])->assertSuccessful();

    expect(Owner::pluck('id')->all())->toBe([$real->id])
        ->and(Pet::count())->toBe(1)
        ->and(User::where('email', 'like', '%@mbopivet.com.py')->count())->toBe(0)
        ->and($realVet->fresh())->not->toBeNull();
});

it('--reset-demo conserva al usuario demo que atendió pacientes de la clínica', function () {
    $this->seed(DemoSeeder::class);

    $vet = User::where('email', 'maria.gonzalez@mbopivet.com.py')->firstOrFail();
    $real = Owner::factory()->create();
    Pet::factory()->for($real)->for($vet)->create();

    $this->artisan('laravet:seed', ['--reset-demo' => true, '--force' => true])
        ->expectsOutputToContain('Se conserva maria.gonzalez@mbopivet.com.py')
        ->assertSuccessful();

    expect($vet->fresh())->not->toBeNull()
        ->and(Pet::count())->toBe(1);
});

it('db:seed también muestra la tabla de usuarios al final', function () {
    $this->artisan('db:seed')
        ->expectsOutputToContain('Usuarios para entrar al panel')
        ->assertSuccessful();
});

it('el demo siembra solo la base del sistema si faltan roles o regiones', function () {
    Role::query()->delete();
    Neighborhood::query()->delete();
    City::query()->delete();
    Department::query()->delete();

    $this->seed(DemoSeeder::class);

    expect(Role::where('name', 'veterinarian')->exists())->toBeTrue()
        ->and(Department::exists())->toBeTrue()
        ->and(Pet::count())->toBe(15);
});

it('en modo interactivo pregunta qué sembrar', function () {
    $this->artisan('laravet:seed')
        ->expectsQuestion('¿Qué quieres sembrar?', ['demo'])
        ->expectsOutputToContain('Datos demo listos')
        ->assertSuccessful();

    expect(Pet::count())->toBe(15);
});
