<?php

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\OwnerResource;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\Vaccination;
use Livewire\Livewire;

it('el rastro de navegación usa el mismo título que la ficha en cada recurso', function () {
    $owner = Owner::factory()->create();
    $consulta = Consultation::factory()->create(['diagnosis' => 'Un diagnóstico muy largo que antes aparecía entero en el rastro']);
    $vacuna = Vaccination::factory()->create();
    $cirugia = Surgery::factory()->create();
    $prueba = Test::factory()->create();

    expect(OwnerResource::getRecordTitle($owner))->toBe($owner->full_name)
        ->and(ConsultationResource::getRecordTitle($consulta))->toBe("Consulta de {$consulta->pet->name} · {$consulta->consultation_date->format('d/m/Y')}")
        ->and((string) ConsultationResource::getRecordTitle($consulta))->not->toContain('diagnóstico')
        ->and(VaccinationResource::getRecordTitle($vacuna))->toBe("{$vacuna->vaccine} · {$vacuna->pet->name}")
        ->and(SurgeryResource::getRecordTitle($cirugia))->toBe("{$cirugia->type} · {$cirugia->pet->name}")
        ->and(TestResource::getRecordTitle($prueba))->toBe("{$prueba->type} · {$prueba->pet->name}")
        ->and(OwnerResource::getRecordTitle(null))->toBeNull();
});

it('la lista de vacunaciones abre con las aplicaciones más recientes primero', function () {
    actingAsRole('admin');
    $antigua = Vaccination::factory()->create(['application_date' => today()->subYears(3), 'next_application' => today()->subYears(2)]);
    $reciente = Vaccination::factory()->create(['application_date' => today()->subDays(2), 'next_application' => today()->addYear()]);

    Livewire::test(ListVaccinations::class)
        ->assertCanSeeTableRecords([$reciente, $antigua], inOrder: true);
});

it('la ficha del propietario no muestra etiquetas en inglés y formatea la CI', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create(['ci' => 4512376]);
    Pet::factory()->create(['owner_id' => $owner->id]);

    Livewire::test(OwnerResource\Pages\ViewOwner::class, ['record' => $owner->getRouteKey()])
        ->assertSee('4.512.376')
        ->assertDontSee('Species')
        ->assertDontSee('Breed')
        ->assertDontSee('>Pets<', escape: false);
});

it('un usuario sin ciudad muestra un guion en la tabla', function () {
    actingAsRole('admin');

    $column = Livewire::test(ListUsers::class)->instance()->getTable()->getColumn('city.name');

    expect($column->getPlaceholder())->toBe('—');
});

it('el departamento se llama Cordillera en los datos de siembra', function () {
    $json = file_get_contents(database_path('data/json/departments.json'));

    expect($json)->toContain('"name": "Cordillera"')->not->toContain('Coordillera')
        ->and(Department::where('name', 'Cordillera')->exists())->toBeTrue();
});

it('la migración renombra Coordillera a Cordillera sin crear duplicados', function () {
    $migration = require database_path('migrations/2026_10_25_000000_fix_cordillera_department_name.php');

    Department::where('name', 'Cordillera')->update(['name' => 'Coordillera']);
    $migration->up();

    expect(Department::where('name', 'Coordillera')->count())->toBe(0)
        ->and(Department::where('name', 'Cordillera')->count())->toBe(1);

    // Idempotente.
    $migration->up();
    expect(Department::where('name', 'Cordillera')->count())->toBe(1);

    // Si ya existe uno con el nombre correcto, no toca el antiguo (evita duplicados).
    Department::create(['name' => 'Coordillera', 'capital' => 'X']);
    $migration->up();

    expect(Department::where('name', 'Cordillera')->count())->toBe(1)
        ->and(Department::where('name', 'Coordillera')->count())->toBe(1);
});
