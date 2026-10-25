<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Exporters\VaccinationExporter;
use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVaccinations extends ListRecords
{
    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(VaccinationExporter::class)
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export_data') ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
