<?php

use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\UserGuidePage;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\NeighborhoodResource;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\VaccinationResource;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Filament\Facades\Filament;

it('el menú ordena los grupos como Pacientes, Historial clínico, Configuración, Catálogos y Ayuda', function () {
    actingAsRole('admin');

    $groups = collect(Filament::getNavigation())->map(fn ($group) => $group->getLabel())->filter()->values()->all();

    expect($groups)->toBe(['Pacientes', 'Historial clínico', 'Configuración', 'Catálogos', 'Ayuda']);
});

it('departamentos, ciudades y barrios van en Catálogos y los grupos de ajustes arrancan plegados', function () {
    actingAsRole('admin');

    foreach ([DepartmentResource::class, CityResource::class, NeighborhoodResource::class] as $resource) {
        expect($resource::getNavigationGroup())->toBe('Catálogos');
    }

    $groups = collect(Filament::getNavigation())->keyBy(fn ($group) => $group->getLabel());

    expect($groups['Catálogos']->isCollapsed())->toBeTrue()
        ->and($groups['Configuración']->isCollapsed())->toBeTrue()
        ->and($groups['Pacientes']->isCollapsed())->toBeFalse()
        ->and($groups['Historial clínico']->isCollapsed())->toBeFalse();
});

it('Reportes va en Historial clínico y la Guía de uso en Ayuda', function () {
    actingAsRole('admin');

    expect(ReportsPage::getNavigationGroup())->toBe('Historial clínico')
        ->and(UserGuidePage::getNavigationGroup())->toBe('Ayuda');
});

it('los contadores de cirugías y pruebas cuentan lo pendiente y se ocultan en cero', function () {
    actingAsRole('admin');

    expect(SurgeryResource::getNavigationBadge())->toBeNull()
        ->and(TestResource::getNavigationBadge())->toBeNull()
        ->and(VaccinationResource::getNavigationBadge())->toBeNull();

    Surgery::factory()->count(2)->create(['date' => now()->addDays(2)]);
    Surgery::factory()->create(['date' => now()->addDays(30)]);
    Test::factory()->count(3)->create(['result' => null]);
    Vaccination::factory()->proximaAVencer(2)->create();

    expect(SurgeryResource::getNavigationBadge())->toBe('2')
        ->and(TestResource::getNavigationBadge())->toBe('3')
        ->and(VaccinationResource::getNavigationBadge())->toBe('1');
});

it('el veterinario cuenta solo sus cirugías y pruebas; el administrador, las de toda la clínica', function () {
    $vet = actingAsRole('veterinarian');
    $otro = User::factory()->create();

    Surgery::factory()->count(2)->create(['user_id' => $vet->id, 'date' => now()->addDay()]);
    Surgery::factory()->create(['user_id' => $otro->id, 'date' => now()->addDay()]);
    Test::factory()->create(['user_id' => $vet->id, 'result' => null]);
    Test::factory()->count(2)->create(['user_id' => $otro->id, 'result' => null]);

    expect(SurgeryResource::getNavigationBadge())->toBe('2')
        ->and(TestResource::getNavigationBadge())->toBe('1');

    actingAsRole('admin');

    expect(SurgeryResource::getNavigationBadge())->toBe('3')
        ->and(TestResource::getNavigationBadge())->toBe('3');
});
