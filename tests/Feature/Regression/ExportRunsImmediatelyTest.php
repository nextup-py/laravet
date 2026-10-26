<?php

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
use App\Models\Vaccination;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Producción usa QUEUE_CONNECTION=database y no tiene la tabla `notifications`: el aviso de
 * «completada» fallaba y la persona solo veía «Exportación iniciada». Los exporters fuerzan la
 * conexión sync y el aviso con los botones de descarga sale en la misma respuesta.
 */
it('cada lista genera el archivo en la misma petición aunque la cola del entorno no sea sync', function (string $page, string $exporter, Closure $makeRecord) {
    config(['queue.default' => 'database']);
    Storage::fake('local');
    actingAsRole('admin');
    $makeRecord();

    $columnMap = collect($exporter::getColumns())
        ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    Livewire::test($page)
        ->callAction('export', data: ['columnMap' => $columnMap])
        ->assertHasNoActionErrors()
        ->assertNotified('Exportación completada');

    $export = Export::query()->latest('id')->firstOrFail();

    expect($export->completed_at)->not->toBeNull()
        ->and($export->successful_rows)->toBe(1)
        ->and(Storage::disk('local')->exists("filament_exports/{$export->getKey()}/0000000000000001.csv"))->toBeTrue();
})->with([
    'mascotas' => [ListPets::class, PetExporter::class, fn () => Pet::factory()->create()],
    'propietarios' => [ListOwners::class, OwnerExporter::class, fn () => Owner::factory()->create()],
    'vacunaciones' => [ListVaccinations::class, VaccinationExporter::class, fn () => Vaccination::factory()->create()],
    'consultas' => [ListConsultations::class, ConsultationExporter::class, fn () => Consultation::factory()->create()],
    'cirugías' => [ListSurgeries::class, SurgeryExporter::class, fn () => Surgery::factory()->create()],
    'pruebas' => [ListTests::class, TestExporter::class, fn () => Test::factory()->create()],
]);
