<?php

use App\Filament\Pages\ReportsPage;
use App\Models\Consultation;
use App\Models\ExportLog;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use App\Reports\FixedReport;
use App\Reports\ReportEntity;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function reportRange(): array
{
    return ['from' => today()->subDays(7)->toDateString(), 'to' => today()->toDateString()];
}

function rolWithPermissions(array $permissions): User
{
    $role = Role::create(['name' => 'rol_'.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    return $user;
}

it('la sección de reportes exige el permiso view_reports', function () {
    actingAsRole('veterinarian');
    expect(ReportsPage::canAccess())->toBeTrue();

    actingAsRole('assistant');
    expect(ReportsPage::canAccess())->toBeTrue();

    rolWithPermissions(['view_any_consultation']);
    expect(ReportsPage::canAccess())->toBeFalse();
});

it('la actividad de la clínica cuenta solo el rango pedido', function () {
    $admin = actingAsRole('admin');
    Consultation::factory()->create(['consultation_date' => today()->subDays(2)]);
    Consultation::factory()->create(['consultation_date' => today()->subDays(30)]);
    Surgery::factory()->create(['date' => today()->subDay()]);

    $rows = collect(FixedReport::ClinicActivity->generate(today()->subDays(7), today(), $admin)->rows)->pluck(1, 0);

    expect($rows['Consultas realizadas'])->toBe(1)
        ->and($rows['Cirugías realizadas'])->toBe(1)
        ->and($rows['Pruebas laboratoriales'])->toBe(0);
});

it('el veterinario ve en los reportes solo lo suyo en consultas, cirugías y pruebas', function () {
    $vet = actingAsRole('veterinarian');
    Consultation::factory()->create(['consultation_date' => today(), 'user_id' => $vet->id]);
    Consultation::factory()->create(['consultation_date' => today()]);
    Surgery::factory()->create(['date' => today()]);

    $rows = collect(FixedReport::ClinicActivity->generate(today()->subDay(), today(), $vet)->rows)->pluck(1, 0);
    $porVeterinario = FixedReport::ActivityByVet->generate(today()->subDay(), today(), $vet)->rows;

    expect($rows['Consultas realizadas'])->toBe(1)
        ->and($rows['Cirugías realizadas'])->toBe(0)
        ->and($porVeterinario)->toHaveCount(1)
        ->and($porVeterinario[0][0])->toBe($vet->name);
});

it('el reporte de actividad por veterinario suma cada tipo de registro', function () {
    $admin = actingAsRole('admin');
    $vet = User::factory()->create();
    $vet->assignRole('veterinarian');
    Consultation::factory()->count(2)->create(['consultation_date' => today(), 'user_id' => $vet->id]);
    Vaccination::factory()->create(['application_date' => today(), 'user_id' => $vet->id]);

    $fila = collect(FixedReport::ActivityByVet->generate(today()->subDay(), today(), $admin)->rows)
        ->firstWhere(0, $vet->name);

    expect($fila)->toBe([$vet->name, 2, 1, 0, 0]);
});

it('mascotas por especie separa activas e inactivas', function () {
    $admin = actingAsRole('admin');
    Pet::factory()->create(['species' => 'Canino', 'active' => true]);
    Pet::factory()->create(['species' => 'Canino', 'active' => false]);
    Pet::factory()->create(['species' => 'Felino', 'active' => true]);

    $rows = FixedReport::PetsBySpecies->generate(today(), today(), $admin)->rows;

    expect($rows[0])->toBe(['Canino', 1, 1, 2])
        ->and($rows[1])->toBe(['Felino', 1, 0, 1]);
});

it('cirugías por tipo y pruebas por tipo agrupan el período', function () {
    $admin = actingAsRole('admin');
    Surgery::factory()->count(2)->create(['date' => today(), 'type' => 'Castración']);
    Surgery::factory()->create(['date' => today(), 'type' => 'Cesárea']);
    Test::factory()->create(['date' => today(), 'type' => 'PCR', 'result' => null]);
    Test::factory()->create(['date' => today(), 'type' => 'PCR', 'result' => ['a.pdf']]);

    expect(FixedReport::SurgeriesByType->generate(today(), today(), $admin)->rows)->toBe([['Castración', 2], ['Cesárea', 1]])
        ->and(FixedReport::TestsByType->generate(today(), today(), $admin)->rows)->toBe([['PCR', 2, 1, 1]]);
});

it('las vacunas por vencer traen datos del propietario solo si el rol puede verlos', function () {
    Vaccination::factory()->proximaAVencer(2)->create();

    $admin = actingAsRole('admin');
    $conPropietario = FixedReport::VaccinationsDue->generate(today(), today(), $admin);

    $sinPropietarios = rolWithPermissions(['view_reports', 'view_any_vaccination']);
    $sin = FixedReport::VaccinationsDue->generate(today(), today(), $sinPropietarios);

    expect($conPropietario->headers)->toContain('Propietario', 'Teléfono')
        ->and($conPropietario->includesOwnerData)->toBeTrue()
        ->and($sin->headers)->not->toContain('Propietario')
        ->and($sin->includesOwnerData)->toBeFalse();
});

it('un reporte fijo sobre registros sin permiso no está disponible', function () {
    $user = rolWithPermissions(['view_reports', 'view_any_pet']);

    expect(FixedReport::PetsBySpecies->isAvailableTo($user))->toBeTrue()
        ->and(FixedReport::SurgeriesByType->isAvailableTo($user))->toBeFalse()
        ->and(FixedReport::VaccinationsDue->isAvailableTo($user))->toBeFalse();
});

it('el reporte personalizado genera la vista previa con las columnas elegidas y el filtro de especie', function () {
    actingAsRole('admin');
    $perro = Pet::factory()->create(['species' => 'Canino']);
    $gato = Pet::factory()->create(['species' => 'Felino']);
    Consultation::factory()->create(['pet_id' => $perro->id, 'consultation_date' => today(), 'diagnosis' => 'Otitis']);
    Consultation::factory()->create(['pet_id' => $gato->id, 'consultation_date' => today(), 'diagnosis' => 'Gripe']);

    Livewire::test(ReportsPage::class)
        ->fillForm([
            'report' => 'custom',
            'entity' => 'consultations',
            'columns' => ['pet', 'diagnosis'],
            'species' => 'Canino',
            ...reportRange(),
        ])
        ->call('generate')
        ->assertSet('headers', ['Mascota', 'Diagnóstico'])
        ->assertSet('total', 1)
        ->assertSet('preview', [[$perro->name, 'Otitis']]);
});

it('el reporte personalizado descarta las columnas del propietario si el rol no puede verlos', function () {
    $user = rolWithPermissions(['view_reports', 'view_any_consultation', 'export_data']);
    Consultation::factory()->create(['consultation_date' => today()]);

    expect(ReportEntity::Consultations->columnOptionsFor($user))->not->toHaveKeys(['owner', 'owner_phone']);

    Livewire::test(ReportsPage::class)
        ->fillForm(['report' => 'custom', 'entity' => 'consultations', 'columns' => ['pet', 'owner', 'owner_phone'], ...reportRange()])
        ->call('generate')
        ->assertSet('headers', ['Mascota']);
});

it('descargar un reporte deja constancia en el registro de exportaciones', function () {
    $admin = actingAsRole('admin');
    Consultation::factory()->create(['consultation_date' => today()]);

    Livewire::test(ReportsPage::class)
        ->fillForm(['report' => 'custom', 'entity' => 'consultations', 'columns' => ['pet', 'owner'], ...reportRange()])
        ->callAction('downloadCsv')
        ->assertFileDownloaded();

    $log = ExportLog::latest('id')->first();

    expect($log->list)->toBe('Reporte: Consultas (personalizado)')
        ->and($log->rows)->toBe(1)
        ->and($log->format)->toBe('csv')
        ->and($log->includes_owner_data)->toBeTrue()
        ->and($log->user_id)->toBe($admin->id);
});

it('los reportes se descargan también en Excel y en PDF', function () {
    actingAsRole('admin');
    Surgery::factory()->create(['date' => today(), 'type' => 'Castración']);

    foreach (['downloadXlsx', 'downloadPdf'] as $action) {
        Livewire::test(ReportsPage::class)
            ->fillForm(['report' => FixedReport::SurgeriesByType->value, ...reportRange()])
            ->callAction($action)
            ->assertFileDownloaded();
    }
});

it('sin el permiso de exportar no se ofrecen las descargas', function () {
    rolWithPermissions(['view_reports', 'view_any_consultation']);

    Livewire::test(ReportsPage::class)
        ->assertActionHidden('downloadXlsx')
        ->assertActionHidden('downloadCsv')
        ->assertActionHidden('downloadPdf');
});

it('el usuario guarda, carga y elimina sus reportes personalizados', function () {
    $user = actingAsRole('veterinarian');

    $component = Livewire::test(ReportsPage::class)
        ->fillForm(['report' => 'custom', 'entity' => 'vaccinations', 'columns' => ['vaccine', 'pet'], 'species' => 'Canino', ...reportRange()])
        ->callAction('saveReport', ['name' => 'Vacunas de perros']);

    $saved = $user->savedReports()->first();
    expect($saved->name)->toBe('Vacunas de perros')
        ->and($saved->entity)->toBe(ReportEntity::Vaccinations)
        ->and($saved->config['columns'])->toBe(['vaccine', 'pet']);

    $component
        ->fillForm(['entity' => 'consultations', 'columns' => ['pet'], 'species' => null])
        ->fillForm(['saved' => (string) $saved->id])
        ->assertFormSet(['entity' => 'vaccinations', 'columns' => ['vaccine', 'pet'], 'species' => 'Canino']);

    $component->callAction('deleteSaved');
    expect($user->savedReports()->count())->toBe(0);
});

it('un usuario no ve ni carga los reportes guardados de otro', function () {
    $otro = User::factory()->create();
    $otro->savedReports()->create(['name' => 'Ajeno', 'entity' => 'pets', 'config' => ['columns' => ['name']]]);

    actingAsRole('veterinarian');

    Livewire::test(ReportsPage::class)
        ->fillForm(['report' => 'custom'])
        ->assertFormFieldExists('saved', fn ($field): bool => $field->getOptions() === []);
});

it('todos los tipos de registro generan el reporte personalizado con todas sus columnas', function () {
    actingAsRole('admin');
    Consultation::factory()->create(['consultation_date' => today()]);
    Vaccination::factory()->create(['application_date' => today()]);
    Surgery::factory()->create(['date' => today()]);
    Test::factory()->create(['date' => today()]);
    Pet::factory()->create();

    foreach (ReportEntity::cases() as $entity) {
        Livewire::test(ReportsPage::class)
            ->fillForm([
                'report' => 'custom',
                'entity' => $entity->value,
                'columns' => array_keys($entity->columns()),
                ...reportRange(),
            ])
            ->call('generate')
            ->assertSet('total', fn (int $total): bool => $total >= 1)
            ->assertCount('headers', count($entity->columns()));
    }
});
