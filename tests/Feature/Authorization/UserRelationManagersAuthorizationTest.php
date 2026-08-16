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
 * no existe Policy de Laravel para Pet ni Vaccination — toda su autorización
 * depende de que el código de Filament la implemente explícitamente. Como
 * refuerzo, PetsRelationManager y VaccinationsRelationManager (dentro de
 * UserResource) ahora sí usan HasClinicRelationManagerAuthorization (antes
 * tenían un canViewAny() manual y nada más), así que si alguien les agrega
 * acciones de crear/editar/eliminar a futuro, quedan protegidas por el mismo
 * criterio de roles que el resto del panel en vez de quedar abiertas.
 */
it('DOCUMENTA EL GAP: no existe Policy para Pet ni Vaccination', function () {
    expect(Gate::getPolicyFor(Pet::class))->toBeNull()
        ->and(Gate::getPolicyFor(Vaccination::class))->toBeNull();
});

it('las RelationManagers de UserResource usan el trait de autorización clínica', function () {
    expect(class_uses(PetsRelationManager::class))
        ->toHaveKey(HasClinicRelationManagerAuthorization::class)
        ->and(class_uses(VaccinationsRelationManager::class))
        ->toHaveKey(HasClinicRelationManagerAuthorization::class);
});

it('siguen sin registrar acciones de crear/editar/eliminar (permanecen de solo lectura)', function () {
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
