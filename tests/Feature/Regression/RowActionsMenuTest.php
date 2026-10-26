<?php

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Filament\Resources\TestResource\Pages\ListTests;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Models\Consultation;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('las tablas clínicas no repiten «Ver» en el menú de fila: el clic en la fila ya abre la ficha', function (string $page) {
    actingAsRole('admin');

    Livewire::test($page)->assertTableActionDoesNotExist('view');
})->with([
    ListPets::class, ListOwners::class, ListConsultations::class,
    ListVaccinations::class, ListSurgeries::class, ListTests::class,
]);

it('consultas, cirugías y pruebas ofrecen PDF y atajo a la mascota desde la fila', function () {
    actingAsRole('admin');
    $consultation = Consultation::factory()->create();
    $surgery = Surgery::factory()->create();
    $test = Test::factory()->create();

    Livewire::test(ListConsultations::class)
        ->assertTableActionExists('downloadReport')
        ->callTableAction('downloadReport', $consultation)
        ->assertFileDownloaded();
    Livewire::test(ListSurgeries::class)
        ->callTableAction('downloadCertificate', $surgery)
        ->assertFileDownloaded();
    Livewire::test(ListTests::class)
        ->callTableAction('downloadReport', $test)
        ->assertFileDownloaded();

    Livewire::test(ListConsultations::class)
        ->assertTableActionHasUrl('viewPet', PetResource::getUrl('view', ['record' => $consultation->pet_id]), $consultation);
});

it('quien no puede ver el registro no descarga su PDF desde la fila', function () {
    $role = Role::create(['name' => 'solo-lista', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_consultation', 'view_any_surgery', 'view_any_test']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    Livewire::test(ListConsultations::class)
        ->assertTableActionHidden('downloadReport', Consultation::factory()->create());
    Livewire::test(ListSurgeries::class)
        ->assertTableActionHidden('downloadCertificate', Surgery::factory()->create());
    Livewire::test(ListTests::class)
        ->assertTableActionHidden('downloadReport', Test::factory()->create());
});

it('la consulta de control abre el alta con la mascota y solo para mascotas activas', function () {
    actingAsRole('admin');
    $activa = Consultation::factory()->create();
    $activa->pet->update(['active' => true]);
    $inactiva = Consultation::factory()->create();
    $inactiva->pet->update(['active' => false]);

    Livewire::test(ListConsultations::class)
        ->assertTableActionHasUrl('followUp', ConsultationResource::getUrl('create', ['pet_id' => $activa->pet_id]), $activa)
        ->assertTableActionHidden('followUp', $inactiva);
});

it('las pruebas pendientes ofrecen cargar resultado desde la fila', function () {
    actingAsRole('admin');
    $test = Test::factory()->create(['result' => null]);

    Livewire::test(ListTests::class)->assertTableActionVisible('uploadResult', $test);
});
