<?php

use App\Filament\Concerns\ClinicRoles;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\User;
use App\Support\DemoPetPhotos;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PetSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config(['filament.default_filesystem_disk' => 'public']);

    $this->source = sys_get_temp_dir().'/demo-pet-photos-'.uniqid();
    File::ensureDirectoryExists($this->source);
    DemoPetPhotos::$sourcePath = $this->source;

    $this->demoOwner = Owner::factory()->create(['ci' => OwnerSeeder::OWNERS[0]['ci']]);
    $this->toby = Pet::factory()->create(['owner_id' => $this->demoOwner->id, 'name' => 'Toby', 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'image' => null]);
});

afterEach(function () {
    DemoPetPhotos::$sourcePath = null;
    File::deleteDirectory($this->source);
});

function demoImage(string $slug, string $contents = 'jpg-bytes'): void
{
    file_put_contents(test()->source.'/'.$slug.'.jpg', $contents);
}

it('asigna la foto por especie y raza a la mascota demo y la copia al disco público', function () {
    demoImage('canino-labrador-retriever');

    $result = DemoPetPhotos::apply();

    expect($result['assigned'])->toBe(1)
        ->and($this->toby->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg');
    Storage::disk('public')->assertExists('demo-pets/canino-labrador-retriever.jpg');
});

it('no toca mascotas que no son del demo aunque tengan el mismo nombre y raza', function () {
    demoImage('canino-labrador-retriever');
    $otherOwner = Owner::factory()->create(['ci' => 9999991]);
    $real = Pet::factory()->create(['owner_id' => $otherOwner->id, 'name' => 'Toby', 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'image' => null]);

    DemoPetPhotos::apply();

    expect($real->fresh()->image)->toBeNull();
});

it('conserva la foto que ya tiene la mascota salvo que se pida sobrescribir', function () {
    demoImage('canino-labrador-retriever');
    $this->toby->update(['image' => 'mi-foto.jpg']);

    expect(DemoPetPhotos::apply()['assigned'])->toBe(0)
        ->and($this->toby->fresh()->image)->toBe('mi-foto.jpg');

    expect(DemoPetPhotos::apply(overwrite: true)['assigned'])->toBe(1)
        ->and($this->toby->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg');
});

it('si falta la imagen de una raza deja la mascota sin foto y lo informa', function () {
    $result = DemoPetPhotos::apply();

    expect($result['assigned'])->toBe(0)
        ->and($result['missing_images'])->toContain('canino-labrador-retriever')
        ->and($this->toby->fresh()->image)->toBeNull();
});

it('el comando en simulación no escribe nada', function () {
    demoImage('canino-labrador-retriever');

    $this->artisan('laravet:demo-photos', ['--dry-run' => true])->assertSuccessful();

    expect($this->toby->fresh()->image)->toBeNull();
    Storage::disk('public')->assertMissing('demo-pets/canino-labrador-retriever.jpg');
});

it('en producción el comando exige --force y con él asigna las fotos', function () {
    demoImage('canino-labrador-retriever');
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('laravet:demo-photos')->expectsConfirmation('Are you sure you want to run this command?', 'no')->assertFailed();
    expect($this->toby->fresh()->image)->toBeNull();

    $this->artisan('laravet:demo-photos', ['--force' => true])->assertSuccessful();
    expect($this->toby->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg');
});

it('volver a correr el PetSeeder no borra las fotos asignadas', function () {
    demoImage('canino-labrador-retriever');
    DemoPetPhotos::apply();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OwnerSeeder::class);
    User::factory()->create(['email' => 'dra.gonzalez@mbopivet.com.py'])->assignRole(ClinicRoles::VETERINARIAN);
    $this->seed(PetSeeder::class);

    expect(Pet::where('name', 'Toby')->first()->image)->toBe('demo-pets/canino-labrador-retriever.jpg');
});
