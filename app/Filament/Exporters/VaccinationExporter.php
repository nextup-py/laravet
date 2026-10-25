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
            static::text('pet.name', 'Mascota'),
            ...(static::canExportOwnerData() ? [
                static::text('pet.owner.full_name', 'Propietario'),
                ExportColumn::make('pet.owner.phone')->label('Teléfono del propietario'),
            ] : []),
            static::text('vaccine', 'Vacuna'),
            ExportColumn::make('application_date')->label('Fecha de aplicación')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('next_application')->label('Próxima aplicación')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('status')->label('Estado')->formatStateUsing(fn ($state) => $state?->getLabel()),
            static::text('batch', 'Lote'),
            static::text('manufacturer', 'Fabricante'),
            static::text('user.name', 'Veterinario'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "vacunaciones-{$export->getKey()}";
    }
}
