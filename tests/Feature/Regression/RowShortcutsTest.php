<?php

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Vaccination;
use Livewire\Livewire;

it('la fila de mascota ofrece atajos de alta con la mascota precargada', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['active' => true]);

    Livewire::test(ListPets::class)
        ->assertTableActionVisible('register_consultation', $pet)
        ->assertTableActionHasUrl('register_consultation', ConsultationResource::getUrl('create', ['pet_id' => $pet->id]), $pet)
        ->assertTableActionHasUrl('register_vaccination', VaccinationResource::getUrl('create', ['pet_id' => $pet->id]), $pet)
        ->assertTableActionHasUrl('register_surgery', SurgeryResource::getUrl('create', ['pet_id' => $pet->id]), $pet);
});

it('las mascotas inactivas no ofrecen atajos de alta', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['active' => false]);

    Livewire::test(ListPets::class)
        ->filterTable('active', false)
        ->assertTableActionHidden('register_consultation', $pet);
});

it('el atajo de consulta precarga la mascota en el formulario', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['active' => true]);

    Livewire::withQueryParams(['pet_id' => $pet->id])
        ->test(CreateConsultation::class)
        ->assertFormSet(['pet_id' => (string) $pet->id]);
});

it('la fila de propietario ofrece registrar mascota con el propietario precargado', function () {
    actingAsRole('admin');
    $owner = Owner::factory()->create();

    Livewire::test(ListOwners::class)
        ->assertTableActionHasUrl('registerPet', PetResource::getUrl('create', ['owner_id' => $owner->id]), $owner);
});

it('avisar por WhatsApp solo aparece en vacunaciones vencidas o por vencer', function () {
    actingAsRole('admin');
    $vencida = Vaccination::factory()->create(['next_application' => today()->subDays(2)]);
    $alDia = Vaccination::factory()->create(['next_application' => today()->addMonths(6)]);

    Livewire::test(ListVaccinations::class)
        ->assertTableActionVisible('whatsappReminder', $vencida)
        ->assertTableActionHidden('whatsappReminder', $alDia);
});
