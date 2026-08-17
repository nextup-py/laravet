<?php

use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\PetsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\VaccinationsRelationManager;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Este archivo documentaba un gap de defensa-en-profundidad: no existía Policy
 * de Laravel para Pet ni Vaccination, así que si alguien agregaba acciones de
 * crear/editar/eliminar a PetsRelationManager/VaccinationsRelationManager
 * (dentro de UserResource) sin acordarse de protegerlas, quedaban abiertas.
 * `php artisan shield:generate` resolvió esto generando Policies reales para
 * todos los modelos con Resource — ahora se confirma que existen y que el
 * criterio de roles se respeta en la práctica.
 */
it('existe Policy para Pet y Vaccination', function () {
    expect(Gate::getPolicyFor(Pet::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(Vaccination::class))->not->toBeNull();
});

it('un asistente puede ver y editar mascotas, pero no eliminarlas ni tocar vacunaciones ajenas', function () {
    actingAsRole('assistant');
    $targetUser = User::factory()->create();
    $pet = Pet::factory()->create(['user_id' => $targetUser->id]);
    $vaccination = Vaccination::factory()->create(['user_id' => $targetUser->id]);

    expect(Gate::allows('view', $pet))->toBeTrue()
        ->and(Gate::allows('update', $pet))->toBeTrue()
        ->and(Gate::allows('delete', $pet))->toBeFalse()
        ->and(Gate::allows('view', $vaccination))->toBeTrue()
        ->and(Gate::allows('update', $vaccination))->toBeFalse()
        ->and(Gate::allows('delete', $vaccination))->toBeFalse();
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
