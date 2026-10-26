<?php

use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Filament\Resources\PetResource\RelationManagers\SurgeriesRelationManager;
use App\Filament\Resources\PetResource\RelationManagers\TestsRelationManager;
use App\Filament\Resources\PetResource\RelationManagers\VaccinationsRelationManager as PetVaccinationsRelationManager;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\VaccinationsRelationManager as UserVaccinationsRelationManager;
use App\Filament\Resources\VaccinationResource;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;

it('los RM de la ficha de mascota ofrecen «Abrir ficha» y abren la lectura en modal al hacer clic en la fila', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);
    $vaccination = Vaccination::factory()->create(['pet_id' => $pet->id]);
    $surgery = Surgery::factory()->create(['pet_id' => $pet->id]);
    $test = Test::factory()->create(['pet_id' => $pet->id]);

    foreach ([
        [ConsultationsRelationManager::class, $consultation],
        [PetVaccinationsRelationManager::class, $vaccination],
        [SurgeriesRelationManager::class, $surgery],
        [TestsRelationManager::class, $test],
    ] as [$manager, $record]) {
        $component = Livewire::test($manager, ['ownerRecord' => $pet, 'pageClass' => ViewPet::class])
            ->assertTableActionExists('openRecord')
            ->assertTableActionVisible('openRecord', $record)
            ->assertTableActionExists('edit');

        // El clic en la fila abre la lectura en un modal (sin sacar al usuario de la mascota).
        expect($component->instance()->getTable()->getRecordAction($record))->toBe('view');

        $component->mountTableAction('view', $record)->assertTableActionMounted('view');
    }
});

it('el RM de vacunaciones de la ficha de mascota ofrece "Revacunar"', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $vaccination = Vaccination::factory()->create(['pet_id' => $pet->id]);

    Livewire::test(PetVaccinationsRelationManager::class, ['ownerRecord' => $pet, 'pageClass' => ViewPet::class])
        ->assertTableActionVisible('revaccinate', $vaccination)
        ->assertTableActionHasUrl('revaccinate', VaccinationResource::getUrl('create', [
            'pet_id' => $pet->id,
            'vaccine' => $vaccination->vaccine,
        ]), $vaccination);
});

it('el RM de vacunaciones del usuario muestra estado y enlaza a la vacunación', function () {
    actingAsRole('admin');
    $user = User::factory()->create();
    $vaccination = Vaccination::factory()->create(['user_id' => $user->id]);

    $component = Livewire::test(UserVaccinationsRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ])
        ->assertCanSeeTableRecords([$vaccination])
        ->assertTableColumnExists('status');

    expect($component->instance()->getTable()->getRecordUrl($vaccination))
        ->toBe(VaccinationResource::getUrl('view', ['record' => $vaccination]));
});
