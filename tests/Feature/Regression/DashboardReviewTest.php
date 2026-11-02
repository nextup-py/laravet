<?php

use App\Filament\Resources\ExportLogResource\Pages\ListExportLogs;
use App\Filament\Resources\ProblemReportResource\Pages\ManageProblemReports;
use App\Filament\Widgets\PetSpeciesOverview;
use App\Filament\Widgets\RecentConsultationsWidget;
use App\Filament\Widgets\TodayOverviewWidget;
use App\Filament\Widgets\UpcomingVaccinationsWidget;
use App\Models\Pet;
use App\Models\ProblemReport;
use Livewire\Livewire;

it('las tarjetas del día enlazan a su lista ya filtrada', function () {
    actingAsRole('admin');

    $html = Livewire::test(TodayOverviewWidget::class)->html();

    expect($html)->toContain('/consultas?')
        ->toContain('/cirugias?')
        ->toContain('/pruebas?')
        ->toContain('activeTab=Pendiente')
        ->and(urldecode(html_entity_decode($html)))->toContain('tableFilters[consultation_date][from]='.today()->toDateString());
});

it('para el veterinario las tarjetas abren la lista solo con sus registros', function () {
    $vet = actingAsRole('veterinarian');

    $html = urldecode(html_entity_decode(Livewire::test(TodayOverviewWidget::class)->html()));

    expect($html)->toContain('tableFilters[user_id][value]='.$vet->id);
});

it('el widget de vacunas se llama «vencidas o por vencer» y usa un encabezado corto', function () {
    actingAsRole('admin');

    Livewire::test(UpcomingVaccinationsWidget::class)
        ->assertSee('Vacunas vencidas o por vencer')
        ->assertDontSee('Próxima aplicación');
});

it('las consultas recientes ocultan la columna Veterinario al veterinario pero no al administrador', function () {
    actingAsRole('veterinarian');
    Livewire::test(RecentConsultationsWidget::class)->assertTableColumnHidden('user.name');

    actingAsRole('admin');
    Livewire::test(RecentConsultationsWidget::class)->assertTableColumnVisible('user.name');
});

it('el gráfico de especies usa el color ámbar del panel', function () {
    actingAsRole('admin');
    Pet::factory()->count(2)->create();

    Livewire::test(PetSpeciesOverview::class)->assertSuccessful();

    $widget = new PetSpeciesOverview;
    $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

    expect($data['datasets'][0]['backgroundColor'])->toBe('#f59e0b');
});

it('Reportes de problemas tiene su título, descripción acotada y estado vacío propio', function () {
    actingAsRole('admin');

    Livewire::test(ManageProblemReports::class)
        ->assertSee('Reportes de problemas')
        ->assertDontSee('Reportes De Problemas')
        ->assertSee('Todavía no hay reportes');

    $reporte = ProblemReport::factory()->create(['description' => str_repeat('palabra ', 30)]);

    Livewire::test(ManageProblemReports::class)->assertCanSeeTableRecords([$reporte])->assertDontSee('Todavía no hay reportes');
});

it('el registro de exportaciones se llama como en el menú y tiene estado vacío propio', function () {
    actingAsRole('admin');

    Livewire::test(ListExportLogs::class)
        ->assertSee('Registro de exportaciones')
        ->assertSee('Todavía no hay exportaciones registradas');
});
