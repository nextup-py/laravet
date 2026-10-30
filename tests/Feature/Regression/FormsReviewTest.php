<?php

use App\Enums\VaccinationStatus;
use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\PetResource\Pages\CreatePet;
use App\Filament\Resources\SurgeryResource\Pages\CreateSurgery;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\VaccinationResource\Pages\CreateVaccination;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Vaccination;
use Livewire\Livewire;

it('registra una mascota sin imagen', function () {
    actingAsRole('admin');
    $modelo = Pet::factory()->make();

    Livewire::test(CreatePet::class)
        ->fillForm([
            'owner_id' => Owner::factory()->create()->id,
            'name' => 'Toby',
            'species' => $modelo->species,
            'breed' => 'Mestizo',
            'birthdate' => now()->subYears(2)->format('Y-m-d'),
            'gender' => $modelo->gender,
            'reproduction' => $modelo->reproduction,
            'size' => $modelo->size,
            'weight' => 12.5,
            'fur' => 'Corto',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Pet::where('name', 'Toby')->first()->image)->toBeNull();
});

it('una vacunación sin próxima aplicación queda al día y no genera alertas', function () {
    $unica = Vaccination::factory()->create(['next_application' => null]);

    expect($unica->fresh()->status)->toBe(VaccinationStatus::UpToDate)
        ->and(Vaccination::withStatus(VaccinationStatus::UpToDate)->pluck('id')->all())->toContain($unica->id)
        ->and(Vaccination::withStatus(VaccinationStatus::Overdue)->pluck('id')->all())->not->toContain($unica->id)
        ->and(Vaccination::withStatus(VaccinationStatus::DueSoon)->pluck('id')->all())->not->toContain($unica->id)
        ->and(Vaccination::actionable()->pluck('id')->all())->not->toContain($unica->id);
});

it('permite registrar una vacunación sin próxima aplicación', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateVaccination::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'vaccine' => 'Rabia',
            'application_date' => today()->format('Y-m-d'),
            'next_application' => null,
            'batch' => 'LOT-1',
            'manufacturer' => 'Zoetis',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    test()->assertDatabaseHas('vaccinations', ['pet_id' => $pet->id, 'next_application' => null]);
});

it('guarda peso y temperatura de la consulta y valida sus rangos', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    $datos = [
        'pet_id' => $pet->id,
        'consultation_date' => now()->format('Y-m-d'),
        'anamnesis' => 'Anamnesis',
        'diagnosis' => 'Diagnóstico',
        'treatment' => 'Tratamiento',
    ];

    Livewire::test(CreateConsultation::class)
        ->fillForm($datos + ['weight' => 12.5, 'temperature' => 38.5])
        ->call('create')
        ->assertHasNoFormErrors();

    $consulta = Consultation::where('pet_id', $pet->id)->first();
    expect((float) $consulta->weight)->toBe(12.5)
        ->and((float) $consulta->temperature)->toBe(38.5);

    Livewire::test(CreateConsultation::class)
        ->fillForm($datos + ['weight' => 200, 'temperature' => 50])
        ->call('create')
        ->assertHasFormErrors(['weight', 'temperature']);
});

it('la consulta sin peso ni temperatura sigue siendo válida', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'consultation_date' => now()->format('Y-m-d'),
            'anamnesis' => 'Anamnesis',
            'diagnosis' => 'Diagnóstico',
            'treatment' => 'Tratamiento',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('limita las observaciones a 5000 caracteres', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateSurgery::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'date' => today()->format('Y-m-d'),
            'type' => 'Castración',
            'observation' => str_repeat('a', 5001),
        ])
        ->call('create')
        ->assertHasFormErrors(['observation']);
});

it('valida el teléfono del personal con el formato paraguayo', function () {
    actingAsRole('admin');

    Livewire::test(CreateUser::class)
        ->fillForm(['phone' => '12345'])
        ->call('create')
        ->assertHasFormErrors(['phone' => 'regex']);
});
