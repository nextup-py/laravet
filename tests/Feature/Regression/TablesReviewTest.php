<?php

use App\Enums\VaccinationStatus;
use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\Vaccination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('mascotas y propietarios se ordenan por nombre por defecto', function () {
    actingAsRole('admin');
    $zeta = Pet::factory()->create(['name' => 'Zeta']);
    $alfa = Pet::factory()->create(['name' => 'Alfa']);

    Livewire::test(ListPets::class)->assertCanSeeTableRecords([$alfa, $zeta], inOrder: true);

    $lopez = Owner::factory()->create(['last_name' => 'López', 'first_name' => 'Zoe']);
    $gomez = Owner::factory()->create(['last_name' => 'Gómez', 'first_name' => 'Ana']);
    $lopezAna = Owner::factory()->create(['last_name' => 'López', 'first_name' => 'Ana']);

    Livewire::test(ListOwners::class)->assertCanSeeTableRecords([$gomez, $lopezAna, $lopez], inOrder: true);
});

it('las pestañas de vacunaciones separan por estado y «Todos» abre sin filtros', function () {
    actingAsRole('admin');
    $vencida = Vaccination::factory()->create(['application_date' => today()->subYear(), 'next_application' => today()->subDays(10)]);
    $alDia = Vaccination::factory()->create(['application_date' => today()->subDays(5), 'next_application' => today()->addMonths(6)]);
    $sinRefuerzo = Vaccination::factory()->create(['application_date' => today()->subDays(5), 'next_application' => null]);

    Livewire::test(ListVaccinations::class)
        ->assertSet('activeTab', 'Todos')
        ->assertCanSeeTableRecords([$vencida, $alDia, $sinRefuerzo])
        ->set('activeTab', VaccinationStatus::Overdue->getLabel())
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$alDia, $sinRefuerzo])
        ->set('activeTab', VaccinationStatus::UpToDate->getLabel())
        ->assertCanSeeTableRecords([$alDia, $sinRefuerzo])
        ->assertCanNotSeeTableRecords([$vencida]);
});

it('las pestañas de cirugías separan programadas, de hoy y realizadas', function () {
    actingAsRole('admin');
    $programada = Surgery::factory()->create(['date' => today()->addDays(3)]);
    $hoy = Surgery::factory()->create(['date' => today()]);
    $realizada = Surgery::factory()->create(['date' => today()->subDays(3)]);

    Livewire::test(ListSurgeries::class)
        ->assertSet('activeTab', 'Todos')
        ->assertCanSeeTableRecords([$programada, $hoy, $realizada])
        ->set('activeTab', 'Programada')
        ->assertCanSeeTableRecords([$programada])
        ->assertCanNotSeeTableRecords([$hoy, $realizada])
        ->set('activeTab', 'Hoy')
        ->assertCanSeeTableRecords([$hoy])
        ->assertCanNotSeeTableRecords([$programada, $realizada])
        ->set('activeTab', 'Realizada')
        ->assertCanSeeTableRecords([$realizada])
        ->assertCanNotSeeTableRecords([$programada, $hoy]);
});

it('las pestañas de pruebas separan pendientes y con resultado', function () {
    actingAsRole('admin');
    $pendiente = Test::factory()->create(['result' => null]);
    $conResultado = Test::factory()->create(['result' => ['test-results/ejemplo.pdf']]);

    Livewire::test(ListTests::class)
        ->assertSet('activeTab', 'Todos')
        ->assertCanSeeTableRecords([$pendiente, $conResultado])
        ->set('activeTab', 'Pendiente')
        ->assertCanSeeTableRecords([$pendiente])
        ->assertCanNotSeeTableRecords([$conResultado])
        ->set('activeTab', 'Con resultado')
        ->assertCanSeeTableRecords([$conResultado])
        ->assertCanNotSeeTableRecords([$pendiente]);
});

it('la columna del veterinario está oculta por defecto en las cuatro listas clínicas', function (string $list) {
    actingAsRole('admin');

    Livewire::test($list)->assertTableColumnExists('user.name')->assertTableColumnVisible('user.name', false);
})->with([
    ListSurgeries::class,
    ListTests::class,
    ListVaccinations::class,
    ListConsultations::class,
]);

it('las listas clínicas no consultan el propietario una vez por fila', function (string $list, Closure $make) {
    actingAsRole('admin');
    foreach (range(1, 6) as $_) {
        $make();
    }

    $ownerQueries = 0;
    DB::listen(function ($query) use (&$ownerQueries): void {
        if (str_starts_with($query->sql, 'select * from `owners`') || str_starts_with($query->sql, 'select * from "owners"')) {
            $ownerQueries++;
        }
    });

    Livewire::test($list)->assertSuccessful();

    expect($ownerQueries)->toBeLessThanOrEqual(2);
})->with([
    'consultas' => [ListConsultations::class, fn () => Consultation::factory()->create()],
    'vacunaciones' => [ListVaccinations::class, fn () => Vaccination::factory()->create()],
    'cirugías' => [ListSurgeries::class, fn () => Surgery::factory()->create()],
    'pruebas' => [ListTests::class, fn () => Test::factory()->create()],
]);

it('vacunaciones tiene el índice compuesto que acelera «vigente / renovada»', function () {
    expect(Schema::hasIndex('vaccinations', 'vaccinations_pet_vaccine_application_index'))->toBeTrue();
});
