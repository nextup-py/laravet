<?php

namespace App\Filament\Resources\OwnerResource\Pages;

use App\Filament\Exporters\OwnerExporter;
use App\Filament\Resources\OwnerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOwners extends ListRecords
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(OwnerExporter::class)
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export_data') ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
