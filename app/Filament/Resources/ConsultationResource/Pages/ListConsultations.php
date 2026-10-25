<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Exporters\ConsultationExporter;
use App\Filament\Resources\ConsultationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsultations extends ListRecords
{
    protected static string $resource = ConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(ConsultationExporter::class)
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export_data') ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
