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
            static::text('first_name', 'Nombre(s)'),
            static::text('last_name', 'Apellido(s)'),
            ExportColumn::make('gender')->label('Género')->formatStateUsing(fn ($state) => $state?->getLabel()),
            static::text('email', 'Correo electrónico'),
            ExportColumn::make('phone')->label('Teléfono'),
            static::text('department.name', 'Departamento'),
            static::text('city.name', 'Ciudad'),
            static::text('neighborhood.name', 'Barrio'),
            static::text('address', 'Dirección'),
            ExportColumn::make('pets_count')->label('Mascotas'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "propietarios-{$export->getKey()}";
    }
}
