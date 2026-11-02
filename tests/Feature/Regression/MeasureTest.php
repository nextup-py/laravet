<?php

use App\Support\Measure;

it('formatea con coma y sin ceros sobrantes', function (mixed $valor, string $esperado) {
    expect(Measure::number($valor))->toBe($esperado);
})->with([
    [8, '8'],
    [8.0, '8'],
    ['28.40', '28,4'],
    [6.2, '6,2'],
    [0.35, '0,35'],
    [1234.5, '1.234,5'],
    [null, '—'],
    ['', '—'],
]);

it('agrega la unidad y tolera valores vacíos', function () {
    expect(Measure::kg('28.40'))->toBe('28,4 kg')
        ->and(Measure::kg(8))->toBe('8 kg')
        ->and(Measure::kg(null))->toBe('—')
        ->and(Measure::celsius('39.10'))->toBe('39,1 °C')
        ->and(Measure::celsius(null))->toBe('—');
});
