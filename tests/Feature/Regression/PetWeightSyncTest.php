<?php

use App\Enums\PetWeightSource;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\PetWeight;

it('una mascota nueva queda con su primer pesaje', function () {
    $pet = Pet::factory()->create(['weight' => 12.5]);

    expect($pet->weights()->count())->toBe(1)
        ->and((float) $pet->weights()->first()->weight)->toBe(12.5)
        ->and($pet->weights()->first()->source)->toBe(PetWeightSource::Registration);
});

it('cambiar a mano el peso de la mascota agrega un pesaje y conserva el historial', function () {
    $pet = Pet::factory()->create(['weight' => 10]);

    $pet->update(['weight' => 11.5]);

    expect($pet->weights()->count())->toBe(2)
        ->and($pet->weights()->latest('id')->first()->source)->toBe(PetWeightSource::Edit)
        ->and((float) $pet->fresh()->weight)->toBe(11.5);
});

it('la consulta con peso crea un pesaje con su fecha y actualiza el peso actual si es la más reciente', function () {
    $pet = Pet::factory()->create(['weight' => 10]);

    $consulta = Consultation::factory()->create([
        'pet_id' => $pet->id,
        'consultation_date' => today()->addDay()->toDateString(),
        'weight' => 13.2,
    ]);

    $pesaje = PetWeight::where('consultation_id', $consulta->id)->first();
    expect((float) $pesaje->weight)->toBe(13.2)
        ->and($pesaje->source)->toBe(PetWeightSource::Consultation)
        ->and($pesaje->weighed_at->toDateString())->toBe(today()->addDay()->toDateString())
        ->and((float) $pet->fresh()->weight)->toBe(13.2);
});

it('una consulta antigua no pisa el peso actual', function () {
    $pet = Pet::factory()->create(['weight' => 10]);
    Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->toDateString(), 'weight' => 14]);

    Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subMonths(3)->toDateString(), 'weight' => 9]);

    expect((float) $pet->fresh()->weight)->toBe(14.0)
        ->and($pet->weights()->count())->toBe(3);
});

it('editar, quitar el peso o borrar la consulta se refleja en el historial y en el peso actual', function () {
    $pet = Pet::factory()->create(['weight' => 10]);
    $pet->weights()->update(['weighed_at' => today()->subDays(60)->toDateString()]);
    $primera = Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subDays(20)->toDateString(), 'weight' => 11]);
    $segunda = Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subDays(5)->toDateString(), 'weight' => 12]);

    $segunda->update(['weight' => 12.8]);
    expect((float) $pet->fresh()->weight)->toBe(12.8)
        ->and(PetWeight::where('consultation_id', $segunda->id)->count())->toBe(1);

    $segunda->update(['weight' => null]);
    expect(PetWeight::where('consultation_id', $segunda->id)->exists())->toBeFalse()
        ->and((float) $pet->fresh()->weight)->toBe(11.0);

    $primera->delete();
    expect(PetWeight::where('consultation_id', $primera->id)->exists())->toBeFalse();
});

it('una consulta sin peso no crea pesaje', function () {
    $pet = Pet::factory()->create();
    $antes = $pet->weights()->count();

    Consultation::factory()->create(['pet_id' => $pet->id, 'weight' => null]);

    expect($pet->weights()->count())->toBe($antes);
});

it('la migración pasa al historial el peso inicial y los pesos ya anotados en consultas, de forma idempotente', function () {
    $pet = Pet::factory()->create(['weight' => 10, 'created_at' => today()->subYear()]);
    $consulta = Consultation::withoutEvents(fn () => Consultation::factory()->create([
        'pet_id' => $pet->id,
        'consultation_date' => today()->subDays(10)->toDateString(),
        'weight' => 12.5,
    ]));
    PetWeight::query()->delete(); // estado previo a la migración: sin historial
    $pet->updateQuietly(['weight' => 10]);

    $migration = require database_path('migrations/2026_11_02_160000_create_pet_weights_table.php');
    $migration->up();
    $migration->up();

    $pesajes = PetWeight::where('pet_id', $pet->id)->orderBy('weighed_at')->get();

    expect($pesajes)->toHaveCount(2)
        ->and($pesajes[0]->weighed_at->toDateString())->toBe(today()->subYear()->toDateString())
        ->and($pesajes[0]->source)->toBe(PetWeightSource::Registration)
        ->and((float) $pesajes[0]->weight)->toBe(10.0)
        ->and($pesajes[1]->consultation_id)->toBe($consulta->id)
        ->and((float) $pesajes[1]->weight)->toBe(12.5)
        ->and((float) $pet->fresh()->weight)->toBe(12.5);
});
