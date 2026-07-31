<?php

use App\Filament\Concerns\HasClinicRelationManagerAuthorization;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\PetsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\VaccinationsRelationManager;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Documenta un gap de defensa-en-profundidad encontrado en el security review:
 * PetsRelationManager y VaccinationsRelationManager (dentro de UserResource) no
 * usan ningún trait de autorización, y no existe Policy para Pet ni Vaccination.
 * Hoy NO es explotable porque ninguno de los dos registra acciones de
 * crear/editar/eliminar en su table() (solo ->columns()) — si alguien agrega
 * esas acciones a futuro sin también agregar el trait, quedarían sin
 * restricción real de rol. Este test debe empezar a fallar (toHaveKey pasa a
 * ser cierto) el día que se corrija agregando el trait correspondiente.
 */
it('DOCUMENTA EL GAP: no existe Policy para Pet ni Vaccination', function () {
    expect(Gate::getPolicyFor(Pet::class))->toBeNull()
        ->and(Gate::getPolicyFor(Vaccination::class))->toBeNull();
});

it('DOCUMENTA EL GAP: las RelationManagers de UserResource no usan el trait de autorización clínica', function () {
    expect(class_uses(PetsRelationManager::class))
        ->not->toHaveKey(HasClinicRelationManagerAuthorization::class)
        ->and(class_uses(VaccinationsRelationManager::class))
        ->not->toHaveKey(HasClinicRelationManagerAuthorization::class);
});

it('confirma que hoy no es explotable: ninguna de las dos RelationManagers registra acciones de crear/editar/eliminar', function () {
    actingAsRole('admin');
    $targetUser = User::factory()->create();
    Pet::factory()->create(['user_id' => $targetUser->id]);
    Vaccination::factory()->create(['user_id' => $targetUser->id]);

    $petsComponent = Livewire::test(PetsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ]);
    $vaccinationsComponent = Livewire::test(VaccinationsRelationManager::class, [
        'ownerRecord' => $targetUser,
        'pageClass' => ViewUser::class,
    ]);

    $petsTable = $petsComponent->instance()->getTable();
    $vaccinationsTable = $vaccinationsComponent->instance()->getTable();

    expect($petsTable->getActions())->toBeEmpty()
        ->and($petsTable->getHeaderActions())->toBeEmpty()
        ->and($vaccinationsTable->getActions())->toBeEmpty()
        ->and($vaccinationsTable->getHeaderActions())->toBeEmpty();
});
