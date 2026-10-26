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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Producción usa QUEUE_CONNECTION=database con un worker. El export va a la cola, el worker arma el
 * archivo y avisa con una notificación en la base de datos (la campanita), que necesita la tabla
 * `notifications`: sin ella el trabajo de aviso fallaba y la persona solo veía «Exportación iniciada».
 *
 * No se fuerza `sync`: el job de Filament hace `auth()->login()` y, dentro de una petición HTTP real,
 * eso rota la sesión y el navegador recibía 419 «Página expirada».
 */
it('cada lista exporta por la cola y avisa con una notificación de base de datos', function (string $page, string $exporter, Closure $makeRecord) {
    config(['queue.default' => 'database']);
    Storage::fake('local');
    $admin = actingAsRole('admin');
    $makeRecord();

    $columnMap = collect($exporter::getColumns())
        ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])
        ->all();

    Livewire::test($page)
        ->callAction('export', data: ['columnMap' => $columnMap])
        ->assertHasNoActionErrors()
        ->assertNotified('Exportación iniciada');

    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);

    $export = Export::query()->latest('id')->firstOrFail();

    expect($export->completed_at)->not->toBeNull()
        ->and($export->successful_rows)->toBe(1)
        ->and(Storage::disk('local')->exists("filament_exports/{$export->getKey()}/0000000000000001.csv"))->toBeTrue()
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($admin->notifications()->first()->data['title'])->toBe('Exportación completada');
})->with([
    'mascotas' => [ListPets::class, PetExporter::class, fn () => Pet::factory()->create()],
    'propietarios' => [ListOwners::class, OwnerExporter::class, fn () => Owner::factory()->create()],
    'vacunaciones' => [ListVaccinations::class, VaccinationExporter::class, fn () => Vaccination::factory()->create()],
    'consultas' => [ListConsultations::class, ConsultationExporter::class, fn () => Consultation::factory()->create()],
    'cirugías' => [ListSurgeries::class, SurgeryExporter::class, fn () => Surgery::factory()->create()],
    'pruebas' => [ListTests::class, TestExporter::class, fn () => Test::factory()->create()],
]);
