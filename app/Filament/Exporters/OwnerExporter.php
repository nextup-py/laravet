<?php

namespace App\Filament\Exporters;

use App\Filament\Exporters\Concerns\ExportsLabels;
use App\Models\Owner;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class OwnerExporter extends Exporter
{
    use ExportsLabels;

    protected static ?string $model = Owner::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['department', 'city', 'neighborhood'])->withCount('pets');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('ci')->label('CI'),
            ExportColumn::make('first_name')->label('Nombre(s)'),
            ExportColumn::make('last_name')->label('Apellido(s)'),
            ExportColumn::make('gender')->label('Género')->formatStateUsing(fn ($state) => $state?->getLabel()),
            ExportColumn::make('email')->label('Correo electrónico'),
            ExportColumn::make('phone')->label('Teléfono'),
            ExportColumn::make('department.name')->label('Departamento'),
            ExportColumn::make('city.name')->label('Ciudad'),
            ExportColumn::make('neighborhood.name')->label('Barrio'),
            ExportColumn::make('address')->label('Dirección'),
            ExportColumn::make('pets_count')->label('Mascotas'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "propietarios-{$export->getKey()}";
    }
}
