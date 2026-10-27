<?php

use App\Filament\Actions\DownloadExport;
use App\Filament\Exporters\ConsultationExporter;
use App\Filament\Exporters\OwnerExporter;
use App\Filament\Exporters\PetExporter;
use App\Filament\Exporters\SurgeryExporter;
use App\Filament\Exporters\TestExporter;
use App\Filament\Exporters\VaccinationExporter;
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
use App\Models\User;
use App\Models\Vaccination;
use Filament\Actions\Exports\ExportColumn;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * «Exportar» arma el archivo en la misma petición y lo descarga al confirmar (sin cola ni campanita).
 * Los trabajos de exportación de Filament hacen `auth()->login()`, que rota la sesión si corren dentro
 * de la petición HTTP, por eso no se usa su `ExportAction`.
 */
function exportColumns(string $exporter): array
{
    return collect($exporter::getColumns())->map(fn (ExportColumn $c): string => $c->getName())->all();
}

function downloadedContent($component): string
{
    return base64_decode($component->effects['download']['content']);
}

afterEach(fn () => DownloadExport::$maxRows = 5000);

it('cada lista descarga el CSV al confirmar', function (string $page, string $exporter, Closure $makeRecord) {
    actingAsRole('admin');
    $makeRecord();

    $component = Livewire::test($page)
        ->callAction('export', data: ['format' => 'csv', 'columns' => exportColumns($exporter)])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();

    $content = downloadedContent($component);
    $firstLabel = $exporter::getColumns()[0]->getLabel();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain($firstLabel)
        ->and(substr_count(trim($content), "\n"))->toBe(1);
})->with([
    'mascotas' => [ListPets::class, PetExporter::class, fn () => Pet::factory()->create()],
    'propietarios' => [ListOwners::class, OwnerExporter::class, fn () => Owner::factory()->create()],
    'vacunaciones' => [ListVaccinations::class, VaccinationExporter::class, fn () => Vaccination::factory()->create()],
    'consultas' => [ListConsultations::class, ConsultationExporter::class, fn () => Consultation::factory()->create()],
    'cirugías' => [ListSurgeries::class, SurgeryExporter::class, fn () => Surgery::factory()->create()],
    'pruebas' => [ListTests::class, TestExporter::class, fn () => Test::factory()->create()],
]);

it('descarga un Excel válido por defecto', function () {
    actingAsRole('admin');
    Surgery::factory()->create();

    $component = Livewire::test(ListSurgeries::class)
        ->callAction('export', data: ['format' => 'xlsx', 'columns' => exportColumns(SurgeryExporter::class)])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();

    expect(downloadedContent($component))->toStartWith('PK')
        ->and($component->effects['download']['name'])->toEndWith('.xlsx');
});

it('solo exporta las columnas elegidas', function () {
    actingAsRole('admin');
    Pet::factory()->create(['name' => 'Firulais']);

    $component = Livewire::test(ListPets::class)
        ->mountAction('export')
        ->set('mountedActionsData.0.format', 'csv')
        ->set('mountedActionsData.0.columns', ['name', 'breed'])
        ->callMountedAction()
        ->assertFileDownloaded();

    $content = downloadedContent($component);

    expect($content)->toContain('Nombre')->toContain('Raza')->toContain('Firulais')
        ->not->toContain('Especie');
});

it('un texto que parece fórmula sale neutralizado en el archivo', function () {
    actingAsRole('admin');
    Consultation::factory()->create(['diagnosis' => '=HYPERLINK("http://x")']);

    $component = Livewire::test(ListConsultations::class)
        ->callAction('export', data: ['format' => 'csv', 'columns' => ['diagnosis']])
        ->assertFileDownloaded();

    expect(downloadedContent($component))->toContain("'=HYPERLINK");
});

it('respeta el filtro de la tabla', function () {
    actingAsRole('admin');
    $perro = Pet::factory()->create(['species' => 'Canino', 'name' => 'Perrito', 'active' => true]);
    Pet::factory()->create(['species' => 'Felino', 'name' => 'Gatito', 'active' => true]);

    $component = Livewire::test(ListPets::class)
        ->filterTable('species', ['Canino'])
        ->callAction('export', data: ['format' => 'csv', 'columns' => ['name']])
        ->assertFileDownloaded();

    expect(downloadedContent($component))->toContain('Perrito')->not->toContain('Gatito');
});

it('avisa y no descarga si hay más filas que el máximo', function () {
    actingAsRole('admin');
    DownloadExport::$maxRows = 1;
    Surgery::factory()->count(2)->create();

    Livewire::test(ListSurgeries::class)
        ->callAction('export', data: ['format' => 'csv', 'columns' => ['type']])
        ->assertNotified('Hay demasiadas filas para exportar')
        ->assertNoFileDownloaded();
});

it('quien no puede ver propietarios no recibe sus datos en el archivo', function () {
    $role = Role::create(['name' => 'sin-propietarios', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet', 'export_data']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);
    Pet::factory()->create();

    $component = Livewire::test(ListPets::class);

    expect(exportColumns(PetExporter::class))->not->toContain('owner.phone');

    $component->callAction('export', data: ['format' => 'csv', 'columns' => exportColumns(PetExporter::class)])
        ->assertFileDownloaded();

    expect(downloadedContent($component))->not->toContain('Teléfono del propietario');
});
