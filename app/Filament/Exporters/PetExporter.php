<?php

namespace App\Filament\Exporters;

use App\Filament\Exporters\Concerns\ExportsLabels;
use App\Models\Pet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class PetExporter extends Exporter
{
    use ExportsLabels;

    protected static ?string $model = Pet::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['owner', 'user']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('name')->label('Nombre'),
            ExportColumn::make('species')->label('Especie')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('breed')->label('Raza'),
            ExportColumn::make('gender')->label('Género')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('birthdate')->label('Fecha de nacimiento')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('size')->label('Tamaño')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('weight')->label('Peso (kg)'),
            ExportColumn::make('fur')->label('Pelaje'),
            ExportColumn::make('reproduction')->label('Reproducción')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('active')->label('Activo')->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No'),
            ...(static::canExportOwnerData() ? [
                ExportColumn::make('owner.full_name')->label('Propietario'),
                ExportColumn::make('owner.phone')->label('Teléfono del propietario'),
            ] : []),
            ExportColumn::make('user.name')->label('Registrada por'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "mascotas-{$export->getKey()}";
    }
}
