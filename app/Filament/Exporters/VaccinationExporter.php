<?php

namespace App\Filament\Exporters;

use App\Filament\Exporters\Concerns\ExportsLabels;
use App\Models\Vaccination;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class VaccinationExporter extends Exporter
{
    use ExportsLabels;

    protected static ?string $model = Vaccination::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->withRenewedFlag()->with(['pet.owner', 'user']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('pet.name')->label('Mascota'),
            ...(static::canExportOwnerData() ? [
                ExportColumn::make('pet.owner.full_name')->label('Propietario'),
                ExportColumn::make('pet.owner.phone')->label('Teléfono del propietario'),
            ] : []),
            ExportColumn::make('vaccine')->label('Vacuna'),
            ExportColumn::make('application_date')->label('Fecha de aplicación')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('next_application')->label('Próxima aplicación')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('status')->label('Estado')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('batch')->label('Lote'),
            ExportColumn::make('manufacturer')->label('Fabricante'),
            ExportColumn::make('user.name')->label('Veterinario'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "vacunaciones-{$export->getKey()}";
    }
}
