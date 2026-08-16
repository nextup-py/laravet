<?php

use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Models\Consultation;
use App\Models\Pet;
use Livewire\Livewire;

/**
 * Las acciones de fila (Ver/Editar/Eliminar) están agrupadas dentro de un
 * Tables\Actions\ActionGroup para reducir el ruido visual. Este test confirma
 * que, aun agrupadas, siguen siendo accesibles y funcionales por su nombre —
 * si alguien las desagrupa o les cambia el nombre sin querer, esto lo detecta.
 */
it('las acciones de fila siguen existiendo y habilitadas dentro del ActionGroup en el form top-level', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create();

    Livewire::test(ListConsultations::class)
        ->assertTableActionExists('view')
        ->assertTableActionExists('edit')
        ->assertTableActionExists('delete')
        ->assertTableActionEnabled('view', $consultation)
        ->assertTableActionEnabled('edit', $consultation)
        ->assertTableActionEnabled('delete', $consultation);
});

it('las acciones de fila siguen existiendo y habilitadas dentro del ActionGroup en la ficha de la mascota', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);

    Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => EditPet::class,
    ])
        ->assertTableActionExists('edit')
        ->assertTableActionExists('delete')
        ->assertTableActionEnabled('edit', $consultation)
        ->assertTableActionEnabled('delete', $consultation);
});
