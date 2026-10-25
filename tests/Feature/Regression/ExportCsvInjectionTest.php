<?php

use App\Filament\Exporters\ConsultationExporter;
use Filament\Actions\Exports\ExportColumn;

it('los textos libres que parecen fórmulas se neutralizan al exportar', function (string $column) {
    actingAsRole('admin');

    $export = collect(ConsultationExporter::getColumns())
        ->first(fn (ExportColumn $c): bool => $c->getName() === $column);

    foreach (['=HYPERLINK("http://x")', '+1+1', '-2+3', '@SUM(A1)'] as $value) {
        expect($export->formatState($value))->toBe("'".$value);
    }

    expect($export->formatState('Otitis externa'))->toBe('Otitis externa');
})->with(['anamnesis', 'diagnosis', 'treatment', 'observation']);
