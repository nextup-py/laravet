<?php

use App\Enums\PetSpecies;
use App\Enums\TestStatus;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\TestResource\Pages\CreateTest;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\TestResource\Pages\ViewTest;
use App\Models\Pet;
use App\Models\Test;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('el estado depende de si hay archivos de resultado', function () {
    expect(Test::factory()->make(['result' => null])->status)->toBe(TestStatus::Pending)
        ->and(Test::factory()->make(['result' => []])->status)->toBe(TestStatus::Pending)
        ->and(Test::factory()->make(['result' => ['a.pdf']])->status)->toBe(TestStatus::WithResult);
});

it('withoutResult incluye nulos y listas vacías, y withResult solo los que tienen archivos', function () {
    $nulo = Test::factory()->create(['result' => null]);
    $vacio = Test::factory()->create(['result' => []]);
    $con = Test::factory()->create(['result' => ['a.pdf']]);

    expect(Test::withoutResult()->pluck('id')->sort()->values()->all())->toBe([$nulo->id, $vacio->id])
        ->and(Test::withResult()->pluck('id')->all())->toBe([$con->id]);
});

it('permite registrar una prueba sin resultado', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateTest::class)
        ->fillForm(['pet_id' => $pet->id, 'date' => today()->toDateString(), 'type' => 'Hematología'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Test::where('pet_id', $pet->id)->first()->status)->toBe(TestStatus::Pending);
});

it('el campo de resultados usa el disco privado', function () {
    $field = TestResource::resultUpload();

    expect($field->getDiskName())->toBe('test-results')
        ->and($field->getVisibility())->toBe('private');
});

it('el disco de resultados no es accesible por la URL pública /storage', function () {
    expect(config('filesystems.disks.test-results.root'))->not->toContain('public')
        ->and(config('filesystems.disks.test-results.url'))->not->toEndWith('/storage');
});

it('sirve el archivo solo a usuarios con permiso para ver pruebas', function () {
    Storage::fake('test-results');
    Storage::disk('test-results')->put('resultados/informe.pdf', 'contenido');
    $test = Test::factory()->create(['result' => ['resultados/informe.pdf']]);
    $url = route('filament.admin.tests.result', ['test' => $test, 'index' => 0]);

    // Invitado: redirige al login del panel.
    test()->get($url)->assertRedirect();

    // Usuario autenticado sin permiso sobre pruebas.
    $sinPermiso = User::factory()->create();
    $sinPermiso->assignRole(Role::create(['name' => 'sin-pruebas', 'guard_name' => 'web']));
    test()->actingAs($sinPermiso)->get($url)->assertForbidden();

    // Usuario con permiso.
    actingAsRole('admin');
    test()->get($url)->assertOk();
    test()->get(route('filament.admin.tests.result', ['test' => $test, 'index' => 5]))->assertNotFound();
});

it('sigue sirviendo archivos antiguos que todavía están en el disco público', function () {
    Storage::fake('test-results');
    Storage::fake('public');
    Storage::disk('public')->put('viejo.pdf', 'contenido');
    $test = Test::factory()->create(['result' => ['viejo.pdf']]);

    actingAsRole('admin');

    test()->get(route('filament.admin.tests.result', ['test' => $test, 'index' => 0]))->assertOk();
});

it('la migración mueve los archivos del disco público al privado', function () {
    Storage::fake('test-results');
    Storage::fake('public');
    Storage::disk('public')->put('viejo.pdf', 'contenido');
    Test::factory()->create(['result' => ['viejo.pdf']]);

    $migration = require database_path('migrations/2026_10_23_000000_move_test_results_to_private_disk.php');
    $migration->up();
    $migration->up(); // idempotente

    Storage::disk('test-results')->assertExists('viejo.pdf');
    Storage::disk('public')->assertMissing('viejo.pdf');
});

it('la tabla muestra propietario, veterinario y estado', function () {
    actingAsRole('admin');
    $prueba = Test::factory()->create(['result' => null]);

    Livewire::test(ListTests::class)
        ->assertSee($prueba->pet->owner->full_name)
        ->assertSee($prueba->user->name)
        ->assertSee('Pendiente');
});

it('filtra por estado, tipo, fechas, veterinario, mascota y especie', function () {
    actingAsRole('admin');
    $vet = User::factory()->create();
    $a = Test::factory()->for(Pet::factory()->state(['species' => PetSpecies::Canino]))
        ->create(['type' => 'PCR', 'date' => today()->subDays(30), 'user_id' => $vet->id, 'result' => null]);
    $b = Test::factory()->for(Pet::factory()->state(['species' => PetSpecies::Felino]))
        ->create(['type' => 'Cultivo', 'date' => today()->subDays(2), 'result' => ['x.pdf']]);

    Livewire::test(ListTests::class)->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    Livewire::test(ListTests::class)->filterTable('status', 'with_result')
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    Livewire::test(ListTests::class)->filterTable('type', ['PCR'])
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    Livewire::test(ListTests::class)->filterTable('date', ['from' => today()->subDays(5)->toDateString(), 'until' => null])
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    Livewire::test(ListTests::class)->filterTable('user_id', $vet->id)
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    Livewire::test(ListTests::class)->filterTable('pet_id', $b->pet_id)
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    Livewire::test(ListTests::class)->filterTable('species', [PetSpecies::Felino->value])
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
});

it('ordena por fecha descendente por defecto', function () {
    actingAsRole('admin');
    $antigua = Test::factory()->create(['date' => today()->subYear()]);
    $reciente = Test::factory()->create(['date' => today()->subDay()]);

    Livewire::test(ListTests::class)->assertCanSeeTableRecords([$reciente, $antigua], inOrder: true);
});

it('la acción Cargar resultado solo aparece en pruebas pendientes y guarda los archivos', function () {
    actingAsRole('admin');
    $pendiente = Test::factory()->create(['result' => null]);
    $completa = Test::factory()->create(['result' => ['a.pdf']]);

    Livewire::test(ListTests::class)
        ->assertTableActionVisible('uploadResult', $pendiente)
        ->assertTableActionHidden('uploadResult', $completa);

    Storage::fake('test-results');
    $archivo = UploadedFile::fake()->create('resultado.pdf', 10, 'application/pdf');

    Livewire::test(ListTests::class)
        ->callTableAction('uploadResult', $pendiente, ['result' => [$archivo]])
        ->assertHasNoTableActionErrors();

    expect($pendiente->fresh()->status)->toBe(TestStatus::WithResult);
});

it('el formulario muestra el contexto de la mascota con sus pruebas previas', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['breed' => 'Labrador']);
    Test::factory()->create(['pet_id' => $pet->id, 'type' => 'Serología']);

    Livewire::test(CreateTest::class)
        ->fillForm(['pet_id' => $pet->id])
        ->assertSee('Labrador')
        ->assertSee('Serología');
});

it('registrar prueba desde la ficha de mascota precarga la mascota', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])->assertActionDoesNotExist('registerTest');

    Livewire::withQueryParams(['pet_id' => $pet->id])
        ->test(CreateTest::class)
        ->assertFormSet(['pet_id' => (string) $pet->id]);
});

it('la ficha usa tipo y mascota como título, muestra estado y enlaces autenticados a los archivos', function () {
    actingAsRole('admin');
    $prueba = Test::factory()->create(['result' => ['resultados/informe.pdf']]);

    Livewire::test(ViewTest::class, ['record' => $prueba->getRouteKey()])
        ->assertSee("{$prueba->type} · {$prueba->pet->name}")
        ->assertSee('Con resultado')
        ->assertSee('informe.pdf')
        ->assertSee(route('filament.admin.tests.result', ['test' => $prueba, 'index' => 0]))
        ->assertDontSee('/storage/resultados');
});

it('la ficha no enlaza a la mascota si el usuario no puede verla', function () {
    $role = Role::create(['name' => 'solo-pruebas', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_test', 'view_test']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $prueba = Test::factory()->create();

    Livewire::test(ViewTest::class, ['record' => $prueba->getRouteKey()])
        ->assertSee($prueba->pet->name)
        ->assertDontSee(route('filament.admin.resources.pets.view', ['record' => $prueba->pet_id]));
});

it('la búsqueda global de pruebas incluye mascota, fecha y estado', function () {
    $prueba = Test::factory()->create(['date' => today()->subDay(), 'result' => null]);

    expect(TestResource::getGloballySearchableAttributes())->toContain('type', 'observation', 'pet.name')
        ->and(TestResource::getGlobalSearchResultTitle($prueba))->toContain($prueba->pet->name)
        ->and(TestResource::getGlobalSearchResultDetails($prueba)['Estado'])->toBe('Pendiente');
});

it('solo acepta PDF, PNG o JPG como archivos de resultado', function () {
    expect(TestResource::resultUpload()->getAcceptedFileTypes())->toBe(['application/pdf', 'image/png', 'image/jpeg']);
});

it('muestra inline solo PDF e imágenes seguras y fuerza la descarga de otros tipos', function () {
    Storage::fake('test-results');
    Storage::disk('test-results')->put('resultados/informe.pdf', '%PDF-1.4 contenido');
    Storage::disk('test-results')->put('resultados/malicioso.html', '<script>alert(1)</script>');
    Storage::disk('test-results')->put('resultados/imagen.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
    $test = Test::factory()->create(['result' => ['resultados/informe.pdf', 'resultados/malicioso.html', 'resultados/imagen.svg']]);
    actingAsRole('admin');

    $pdf = test()->get(route('filament.admin.tests.result', ['test' => $test, 'index' => 0]));
    $pdf->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($pdf->headers->get('Content-Disposition'))->not->toContain('attachment');

    foreach ([1, 2] as $index) {
        $respuesta = test()->get(route('filament.admin.tests.result', ['test' => $test, 'index' => $index]));
        $respuesta->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        expect($respuesta->headers->get('Content-Disposition'))->toContain('attachment');
    }
});
