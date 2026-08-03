<?php

use App\Models\Pet;
use App\Services\AIDiagnosticService;
use Illuminate\Support\Facades\Http;

it('retorna diagnóstico y tratamiento cuando la API responde correctamente', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode(['diagnosis' => 'Gastroenteritis', 'treatment' => 'Dieta blanda'])],
            ],
        ]),
    ]);

    $pet = Pet::factory()->create();

    $result = app(AIDiagnosticService::class)->suggest($pet, 'Vómitos y diarrea desde ayer.');

    expect($result)->toBe(['diagnosis' => 'Gastroenteritis', 'treatment' => 'Dieta blanda']);
});

it('lanza una excepción si la API responde con error', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(null, 500),
    ]);

    $pet = Pet::factory()->create();

    app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
})->throws(RuntimeException::class);

it('lanza una excepción si la respuesta no tiene el formato JSON esperado', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => 'esto no es JSON válido'],
            ],
        ]),
    ]);

    $pet = Pet::factory()->create();

    app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
})->throws(RuntimeException::class);

it('lanza una excepción sin llamar a la API si falta la API key', function () {
    config(['services.anthropic.key' => null]);

    Http::fake();

    $pet = Pet::factory()->create();

    try {
        app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
    } catch (RuntimeException) {
        // esperado
    }

    Http::assertNothingSent();
});
