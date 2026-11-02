<?php

use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('la lista de mascotas muestra la última consulta y la próxima vacuna vigente', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subDays(30)->toDateString()]);
    Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subDays(3)->toDateString()]);
    // Renovada: no cuenta; dosis única (sin fecha): no cuenta; vigente: es la próxima.
    Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'application_date' => today()->subYears(2), 'next_application' => today()->subYear()]);
    Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'application_date' => today()->subMonths(2), 'next_application' => today()->addMonths(10)]);
    Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Triple', 'application_date' => today()->subMonths(1), 'next_application' => null]);

    $record = Pet::query()
        ->withMax('consultations', 'consultation_date')
        ->withMin(['vaccinations as next_vaccine_due' => fn ($q) => $q->current()->whereNotNull('next_application')], 'next_application')
        ->find($pet->id);

    expect(substr((string) $record->consultations_max_consultation_date, 0, 10))->toBe(today()->subDays(3)->toDateString())
        ->and(substr((string) $record->next_vaccine_due, 0, 10))->toBe(today()->addMonths(10)->toDateString());

    Livewire::test(ListPets::class)
        ->assertCanSeeTableRecords([$pet])
        ->assertTableColumnStateSet('consultations_max_consultation_date', $record->consultations_max_consultation_date, $record)
        ->assertSee(today()->subDays(3)->format('d/m/Y'))
        ->assertSee(today()->addMonths(10)->format('d/m/Y'))
        ->sortTable('next_vaccine_due')
        ->assertSuccessful();
});

it('sin permiso sobre consultas o vacunas esas columnas no se muestran', function () {
    $viewer = User::factory()->create();
    $role = Role::create(['name' => 'solo-mascotas']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $viewer->assignRole($role);
    $this->actingAs($viewer);
    Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->assertTableColumnHidden('consultations_max_consultation_date')
        ->assertTableColumnHidden('next_vaccine_due');
});

it('la ficha muestra los últimos pesajes de la mascota', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['weight' => 10]);
    $pet->weights()->update(['weighed_at' => today()->subMonths(3)->toDateString()]);
    PetWeight::factory()->create(['pet_id' => $pet->id, 'weight' => 11.25, 'weighed_at' => today()->subDay()->toDateString()]);

    Livewire::test(ViewPet::class, ['record' => $pet->id])
        ->assertSee('Últimos pesajes')
        ->assertSee(today()->subDay()->format('d/m/Y').' · 11,25 kg')
        ->assertSee(today()->subMonths(3)->format('d/m/Y').' · 10 kg');
});
