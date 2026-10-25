<?php

namespace App\Filament\Exporters;

use App\Filament\Exporters\Concerns\ExportsLabels;
use App\Models\Consultation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class ConsultationExporter extends Exporter
{
    use ExportsLabels;

    protected static ?string $model = Consultation::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['pet.owner', 'user']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('consultation_date')->label('Fecha de consulta')->formatStateUsing(fn ($state) => static::formatDate($state)),
            ExportColumn::make('pet.name')->label('Mascota'),
            ...(static::canExportOwnerData() ? [
                ExportColumn::make('pet.owner.full_name')->label('Propietario'),
            ] : []),
            ExportColumn::make('anamnesis')->label('Anamnesis'),
            ExportColumn::make('diagnosis')->label('Diagnóstico'),
            ExportColumn::make('treatment')->label('Tratamiento'),
            ExportColumn::make('observation')->label('Observación'),
            ExportColumn::make('user.name')->label('Veterinario'),
        ];
    }

    public function getFileName(Export $export): string
    {
        return "consultas-{$export->getKey()}";
    }
}
