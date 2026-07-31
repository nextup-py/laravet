<?php

use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\VaccinationResource;

it('las opciones de tipo de cirugía tienen la misma clave y label', function () {
    foreach (SurgeryResource::typeOptions() as $key => $label) {
        expect($key)->toBe($label);
    }

    expect(SurgeryResource::typeOptions())->toHaveKey('Cesárea');
});

it('las opciones de tipo de prueba tienen la misma clave y label', function () {
    foreach (TestResource::typeOptions() as $key => $label) {
        expect($key)->toBe($label);
    }

    expect(TestResource::typeOptions())->toHaveKey('Uroanálisis');
});

it('las opciones de vacuna tienen la misma clave y label', function () {
    foreach (VaccinationResource::vaccineOptions() as $key => $label) {
        expect($key)->toBe($label);
    }

    expect(VaccinationResource::vaccineOptions())
        ->toHaveKey('Séptuple')
        ->toHaveKey('Antirrábica');
});
