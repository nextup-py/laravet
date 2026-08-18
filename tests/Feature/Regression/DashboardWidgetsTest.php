<?php

use App\Enums\PetSpecies;
use App\Filament\Widgets\PetSpeciesOverview;
use App\Filament\Widgets\RecentConsultationsWidget;
use App\Filament\Widgets\TestsWithoutResultWidget;
use App\Filament\Widgets\TodayOverviewWidget;
use App\Filament\Widgets\UpcomingSurgeriesWidget;
use App\Filament\Widgets\UpcomingVaccinationsWidget;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * getCachedStats() es protected en Filament\Widgets\StatsOverviewWidget.
 */
function callProtected(object $instance, string $method): mixed
{
    $reflection = new ReflectionMethod($instance, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($instance);
}

it('el gráfico de especies incluye las 8 especies del enum, no solo caninos y felinos', function () {
    actingAsRole('veterinarian');
    Pet::factory()->create(['species' => PetSpecies::Reptil]);
    Pet::factory()->create(['species' => PetSpecies::Reptil]);
    Pet::factory()->create(['species' => PetSpecies::Canino]);

    $data = callProtected(Livewire::test(PetSpeciesOverview::class)->instance(), 'getData');

    expect($data['labels'])->toHaveCount(8)
        ->and($data['labels'])->toContain('Reptil', 'Canino', 'Bovino', 'Pez');

    $reptilIndex = array_search('Reptil', $data['labels']);
    expect($data['datasets'][0]['data'][$reptilIndex])->toBe(2);
});

it('Surgery::upcoming() incluye cirugías programadas dentro de los próximos 7 días, no las pasadas', function () {
    $futura = Surgery::factory()->create(['date' => now()->addDays(3)]);
    $pasada = Surgery::factory()->create(['date' => now()->subDay()]);
    $lejana = Surgery::factory()->create(['date' => now()->addDays(30)]);

    $ids = Surgery::query()->upcoming()->pluck('id');

    expect($ids)->toContain($futura->id)
        ->not->toContain($pasada->id)
        ->not->toContain($lejana->id);
});

it('Test::withoutResult() solo incluye exámenes sin archivo de resultado cargado', function () {
    $sinResultado = Test::factory()->create(['result' => null]);
    $conResultado = Test::factory()->create(['result' => ['archivo.pdf']]);

    $ids = Test::query()->withoutResult()->pluck('id');

    expect($ids)->toContain($sinResultado->id)
        ->not->toContain($conResultado->id);
});

it('un rol sin ningún permiso clínico no ve ninguno de los widgets del dashboard', function () {
    Role::create(['name' => 'sin_permisos', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('sin_permisos');
    test()->actingAs($user);

    expect(PetSpeciesOverview::canView())->toBeFalse()
        ->and(TodayOverviewWidget::canView())->toBeFalse()
        ->and(UpcomingVaccinationsWidget::canView())->toBeFalse()
        ->and(UpcomingSurgeriesWidget::canView())->toBeFalse()
        ->and(TestsWithoutResultWidget::canView())->toBeFalse()
        ->and(RecentConsultationsWidget::canView())->toBeFalse();
});

it('veterinarian ve todos los widgets del dashboard', function () {
    actingAsRole('veterinarian');

    expect(PetSpeciesOverview::canView())->toBeTrue()
        ->and(TodayOverviewWidget::canView())->toBeTrue()
        ->and(UpcomingVaccinationsWidget::canView())->toBeTrue()
        ->and(UpcomingSurgeriesWidget::canView())->toBeTrue()
        ->and(TestsWithoutResultWidget::canView())->toBeTrue()
        ->and(RecentConsultationsWidget::canView())->toBeTrue();
});

it('un rol con permiso solo sobre cirugías ve el widget de cirugías próximas pero no el de vacunas', function () {
    $role = Role::create(['name' => 'solo_cirugias', 'guard_name' => 'web']);
    $role->givePermissionTo('view_any_surgery');
    $user = User::factory()->create();
    $user->assignRole('solo_cirugias');
    test()->actingAs($user);

    expect(UpcomingSurgeriesWidget::canView())->toBeTrue()
        ->and(UpcomingVaccinationsWidget::canView())->toBeFalse()
        ->and(TestsWithoutResultWidget::canView())->toBeFalse();
});

it('TodayOverviewWidget solo incluye el stat de cirugías si el usuario tiene permiso sobre consultas y pruebas pero no cirugías', function () {
    $role = Role::create(['name' => 'consultas_y_pruebas', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_consultation', 'view_any_test']);
    $user = User::factory()->create();
    $user->assignRole('consultas_y_pruebas');
    test()->actingAs($user);

    $stats = callProtected(Livewire::test(TodayOverviewWidget::class)->instance(), 'getStats');

    $labels = array_map(fn ($stat) => $stat->getLabel(), $stats);

    expect($labels)->toContain('Consultas de hoy')
        ->toContain('Pruebas sin resultado')
        ->not->toContain('Cirugías próximas');
});

it('el widget de cirugías próximas muestra solo las próximas y linkea al registro', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $futura = Surgery::factory()->create(['pet_id' => $pet->id, 'date' => now()->addDays(2)]);
    Surgery::factory()->create(['pet_id' => $pet->id, 'date' => now()->subDays(2)]);

    $records = Livewire::test(UpcomingSurgeriesWidget::class)
        ->instance()
        ->getTable()
        ->getRecords();

    expect($records->pluck('id'))->toContain($futura->id)
        ->and($records)->toHaveCount(1);
});

it('el widget de pruebas sin resultado muestra solo las que no tienen resultado cargado', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $sinResultado = Test::factory()->create(['pet_id' => $pet->id, 'result' => null]);
    Test::factory()->create(['pet_id' => $pet->id, 'result' => ['archivo.pdf']]);

    $records = Livewire::test(TestsWithoutResultWidget::class)
        ->instance()
        ->getTable()
        ->getRecords();

    expect($records->pluck('id'))->toContain($sinResultado->id)
        ->and($records)->toHaveCount(1);
});

it('el stat de consultas de hoy solo cuenta las de la fecha actual', function () {
    actingAsRole('veterinarian');
    Consultation::factory()->create(['consultation_date' => today()]);
    Consultation::factory()->create(['consultation_date' => today()->subDay()]);

    $stats = callProtected(Livewire::test(TodayOverviewWidget::class)->instance(), 'getStats');

    $consultasHoy = collect($stats)->first(fn ($stat) => $stat->getLabel() === 'Consultas de hoy');

    expect($consultasHoy->getValue())->toBe(1);
});
