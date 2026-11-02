<?php

use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Filament\Pages\ClinicSettingsPage;
use App\Models\Pet;
use App\Support\PetSizeSuggestion;
use Livewire\Livewire;

it('propone el tamaño del perro según los límites de la clínica', function (float $kilos, PetSize $esperado) {
    $perro = Pet::factory()->make(['species' => PetSpecies::Canino, 'size' => PetSize::Medium]);

    expect(PetSizeSuggestion::for($perro, $kilos))->toBe($esperado);
})->with([
    [3.0, PetSize::Tiny],
    [5.0, PetSize::Tiny],
    [8.5, PetSize::Small],
    [18.0, PetSize::Medium],
    [30.0, PetSize::Big],
    [60.0, PetSize::Giant],
]);

it('no propone tamaño para una especie sin límites ni para pesos inválidos', function () {
    $gato = Pet::factory()->make(['species' => PetSpecies::Felino]);
    $perro = Pet::factory()->make(['species' => PetSpecies::Canino]);

    expect(PetSizeSuggestion::for($gato, 4))->toBeNull()
        ->and(PetSizeSuggestion::for($perro, 0))->toBeNull();
});

it('differingFor solo devuelve el tamaño si cambia el actual', function () {
    $perro = Pet::factory()->make(['species' => PetSpecies::Canino, 'size' => PetSize::Medium]);

    expect(PetSizeSuggestion::differingFor($perro, 18))->toBeNull()
        ->and(PetSizeSuggestion::differingFor($perro, 40))->toBe(PetSize::Big);
});

it('los límites se editan en Datos de la clínica y se validan en orden creciente', function () {
    actingAsRole('admin');
    clinicEmail();

    Livewire::test(ClinicSettingsPage::class)
        ->fillForm(['size_thresholds' => [['species' => 'Felino', 'tiny' => 2, 'small' => 4, 'medium' => 6, 'big' => 8]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $gato = Pet::factory()->make(['species' => PetSpecies::Felino]);
    expect(PetSizeSuggestion::for($gato, 7))->toBe(PetSize::Big);

    Livewire::test(ClinicSettingsPage::class)
        ->fillForm(['size_thresholds' => [['species' => 'Felino', 'tiny' => 5, 'small' => 4, 'medium' => 6, 'big' => 8]]])
        ->call('save')
        ->assertHasFormErrors();
});
