<?php

use App\Filament\Resources\ConsultationResource\Pages\ViewConsultation;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Vaccination;
use Livewire\Livewire;

it('la ficha de mascota agrupa las descargas y ya no tiene botones de registrar', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionExists('downloadClinicalHistory')
        ->assertActionExists('downloadPetIdCard')
        ->assertActionDoesNotExist('registerSurgery')
        ->assertActionDoesNotExist('registerTest')
        ->assertActionExists('edit');
});

it('la ficha de vacunación ofrece Revacunar con mascota y vacuna precargadas', function () {
    actingAsRole('veterinarian');
    $vaccination = Vaccination::factory()->create();

    Livewire::test(ViewVaccination::class, ['record' => $vaccination->getRouteKey()])
        ->assertActionVisible('revaccinate')
        ->assertActionHasUrl('revaccinate', VaccinationResource::getUrl('create', [
            'pet_id' => $vaccination->pet_id,
            'vaccine' => $vaccination->vaccine,
        ]));
});

it('la ficha de consulta permite descargar el informe en PDF', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create();

    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertActionExists('downloadConsultation')
        ->callAction('downloadConsultation')
        ->assertFileDownloaded();
});
