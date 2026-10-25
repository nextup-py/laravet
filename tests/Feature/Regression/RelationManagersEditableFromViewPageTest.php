<?php

use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Models\Pet;
use Livewire\Livewire;

/**
 * Filament vuelve de solo lectura (por defecto) las RelationManagers en páginas
 * ViewRecord. Este proyecto depende de poder crear registros médicos
 * desde la ficha de la mascota (ver CLAUDE.md), así que ese default se desactiva en
 * AdminPanelProvider. Este test evita que alguien lo reactive sin darse cuenta.
 */
it('la action de crear consultas está habilitada al ver la ficha de la mascota', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => ViewPet::class,
    ])->assertTableActionEnabled('create');
});
