<?php

namespace App\Filament\Exporters;

use App\Filament\Exporters\Concerns\ExportsLabels;
use App\Models\Test;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class TestExporter extends Exporter
{
    use ExportsLabels;

    protected static ?string $model = Test::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['pet.owner', 'user']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('date')->label('Fecha')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('pet.name')->label('Mascota'),
            ...(static::canExportOwnerData() ? [
                ExportColumn::make('pet.owner.full_name')->label('Propietario'),
            ] : []),
            ExportColumn::make('type')->label('Tipo'),
            ExportColumn::make('status')->label('Estado')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('user.name')->label('Veterinario'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "pruebas-{$export->getKey()}";
    }
}
