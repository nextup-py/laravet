<?php

use App\Enums\PetReproductionStatus;
use App\Filament\Resources\SurgeryResource\Pages\CreateSurgery;
use App\Models\Pet;
use App\Models\Surgery;
use App\Support\PetReproductionSync;
use Livewire\Livewire;

it('reconoce los tipos de cirugía que cambian la reproducción', function (string $tipo, ?PetReproductionStatus $esperado) {
    expect(PetReproductionSync::statusForType($tipo))->toBe($esperado);
})->with([
    ['Castración', PetReproductionStatus::Castrated],
    ['castración de felino', PetReproductionStatus::Castrated],
    ['Esterilización', PetReproductionStatus::Sterilized],
    ['Ovariohisterectomía', PetReproductionStatus::Sterilized],
    ['Cirugía Dental', null],
    ['Extracción de Tumores', null],
]);

it('una cirugía de castración ya realizada pasa la mascota a Castrado', function () {
    $pet = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);

    Surgery::factory()->create(['pet_id' => $pet->id, 'type' => 'Castración', 'date' => today()->subDays(2)->toDateString()]);

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Castrated);
});

it('una cirugía de otro tipo o sobre una mascota ya esterilizada no cambia nada', function () {
    $normal = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);
    $esterilizada = Pet::factory()->create(['reproduction' => PetReproductionStatus::Sterilized]);

    Surgery::factory()->create(['pet_id' => $normal->id, 'type' => 'Cirugía Dental', 'date' => today()->toDateString()]);
    Surgery::factory()->create(['pet_id' => $esterilizada->id, 'type' => 'Castración', 'date' => today()->toDateString()]);

    expect($normal->fresh()->reproduction)->toBe(PetReproductionStatus::Normal)
        ->and($esterilizada->fresh()->reproduction)->toBe(PetReproductionStatus::Sterilized);
});

it('una cirugía programada a futuro se aplica cuando llega el día, con el comando diario', function () {
    $pet = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);
    Surgery::factory()->create(['pet_id' => $pet->id, 'type' => 'Esterilización', 'date' => today()->addDays(2)->toDateString()]);

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Normal);

    $this->artisan('pets:sync-reproduction')->assertSuccessful();
    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Normal);

    $this->travelTo(today()->addDays(2)->setHour(1));
    $this->artisan('pets:sync-reproduction')->assertSuccessful();

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Sterilized);
});

it('editar otra cosa de la cirugía no deshace una corrección manual del estado', function () {
    $pet = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);
    $cirugia = Surgery::factory()->create(['pet_id' => $pet->id, 'type' => 'Castración', 'date' => today()->subDay()->toDateString()]);
    $pet->refresh()->update(['reproduction' => PetReproductionStatus::Normal]);

    $cirugia->update(['observation' => 'Sin complicaciones']);

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Normal);
});

it('el comando no toca cirugías de hace más de un día', function () {
    $pet = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);
    $cirugia = Surgery::factory()->create(['pet_id' => $pet->id, 'type' => 'Castración', 'date' => today()->subDays(5)->toDateString()]);
    $pet->refresh()->update(['reproduction' => PetReproductionStatus::Normal]);

    $this->artisan('pets:sync-reproduction')->assertSuccessful();

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Normal);
});

it('al guardar la cirugía desde el panel avisa que cambió el estado reproductivo', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['reproduction' => PetReproductionStatus::Normal]);

    Livewire::test(CreateSurgery::class)
        ->fillForm(['pet_id' => $pet->id, 'date' => today()->toDateString(), 'type' => 'Castración'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Estado reproductivo actualizado');

    expect($pet->fresh()->reproduction)->toBe(PetReproductionStatus::Castrated);
});
