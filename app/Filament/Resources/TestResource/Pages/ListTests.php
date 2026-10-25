<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Exporters\TestExporter;
use App\Filament\Resources\TestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTests extends ListRecords
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(TestExporter::class)
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can('export_data') ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
