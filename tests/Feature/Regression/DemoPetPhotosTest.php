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

it('el repositorio trae una imagen para cada especie y raza de las mascotas demo', function () {
    DemoPetPhotos::$sourcePath = null;

    $faltantes = collect(PetSeeder::PETS)
        ->map(fn (array $pet): string => DemoPetPhotos::slug($pet[1], $pet[2]))
        ->unique()
        ->reject(fn (string $slug): bool => collect(['jpg', 'jpeg', 'png', 'webp'])->contains(fn (string $extension): bool => is_file(database_path("seeders/images/pets/{$slug}.{$extension}"))))
        ->values()
        ->all();

    expect($faltantes)->toBe([]);
});

it('con --all asigna foto a cualquier mascota sin foto cuya especie y raza tengan imagen', function () {
    demoImage('canino-labrador-retriever');
    $otherOwner = Owner::factory()->create();
    $ajena = Pet::factory()->create(['owner_id' => $otherOwner->id, 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'image' => null]);
    $sinImagen = Pet::factory()->create(['owner_id' => $otherOwner->id, 'species' => 'Canino', 'breed' => 'Raza Inexistente', 'image' => null]);
    $conFoto = Pet::factory()->create(['owner_id' => $otherOwner->id, 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'image' => 'mia.jpg']);

    expect(DemoPetPhotos::apply()['assigned'])->toBe(1)
        ->and($ajena->fresh()->image)->toBeNull();

    $result = DemoPetPhotos::apply(all: true);

    expect($result['assigned'])->toBe(1)
        ->and($result['missing_images'])->toContain(DemoPetPhotos::slug('Canino', 'Raza Inexistente'))
        ->and($ajena->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg')
        ->and($sinImagen->fresh()->image)->toBeNull()
        ->and($conFoto->fresh()->image)->toBe('mia.jpg');
});

it('una raza con variantes reparte las fotos de forma estable según el id de la mascota', function () {
    demoImage('canino-srd');
    demoImage('canino-srd-2');
    demoImage('canino-srd-3');
    $owner = Owner::factory()->create();
    $pets = Pet::factory()->count(6)->create(['owner_id' => $owner->id, 'species' => 'Canino', 'breed' => 'SRD', 'image' => null]);

    DemoPetPhotos::apply(all: true);
    $primera = $pets->map(fn (Pet $pet): string => $pet->fresh()->image)->all();

    expect(array_unique($primera))->toHaveCount(3);

    DemoPetPhotos::apply(overwrite: true, all: true);

    expect($pets->map(fn (Pet $pet): string => $pet->fresh()->image)->all())->toBe($primera);
});

it('el comando con --all en simulación resume por raza y no escribe nada', function () {
    demoImage('canino-labrador-retriever');
    $this->toby->update(['image' => null]);

    $this->artisan('laravet:demo-photos', ['--all' => true, '--dry-run' => true])
        ->expectsOutputToContain('canino-labrador-retriever')
        ->assertSuccessful();

    expect($this->toby->fresh()->image)->toBeNull();
});

it('hay imagen para todas las razas que generan los scripts del demo', function () {
    DemoPetPhotos::$sourcePath = null;

    $razas = [
        'Canino' => ['SRD', 'Labrador Retriever', 'Pastor Alemán', 'Poodle', 'Chihuahua', 'Salchicha', 'Bulldog Francés', 'Beagle', 'Rottweiler', 'Cocker Spaniel', 'Schnauzer', 'Pitbull', 'Boxer', 'Yorkshire Terrier', 'Shih Tzu', 'Golden Retriever', 'Pastor Belga', 'Doberman'],
        'Felino' => ['SRD', 'Siamés', 'Persa', 'Angora', 'Maine Coon', 'Bengalí'],
        'Roedor' => ['Conejo Mini Lop', 'Conejo Cabeza de León', 'Cobayo', 'Hámster Sirio'],
        'Ave' => ['Loro Hablador (Amazona)', 'Cotorra', 'Canario'],
    ];

    $faltantes = collect($razas)
        ->flatMap(fn (array $breeds, string $species): array => array_map(fn (string $breed): string => DemoPetPhotos::slug($species, $breed), $breeds))
        ->reject(fn (string $slug): bool => glob(database_path("seeders/images/pets/{$slug}*.jpg")) !== [])
        ->values()
        ->all();

    expect($faltantes)->toBe([]);
});

it('si se reemplaza una imagen del repositorio, actualiza el archivo ya copiado sin cambiar a qué foto apunta la mascota', function () {
    demoImage('canino-labrador-retriever', 'foto-vieja');
    DemoPetPhotos::apply();

    expect($this->toby->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg');
    expect(Storage::disk('public')->get('demo-pets/canino-labrador-retriever.jpg'))->toBe('foto-vieja');

    demoImage('canino-labrador-retriever', 'foto-nueva');
    $result = DemoPetPhotos::apply();

    expect($result['refreshed'])->toBe(1)
        ->and($result['assigned'])->toBe(0)
        ->and($this->toby->fresh()->image)->toBe('demo-pets/canino-labrador-retriever.jpg')
        ->and(Storage::disk('public')->get('demo-pets/canino-labrador-retriever.jpg'))->toBe('foto-nueva');

    expect(DemoPetPhotos::apply()['refreshed'])->toBe(0);
});
