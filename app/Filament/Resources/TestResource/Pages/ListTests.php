<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Actions\DownloadExport;
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
            DownloadExport::make(TestExporter::class, 'pruebas'),
            Actions\CreateAction::make(),
        ];
    }
}
