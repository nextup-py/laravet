<?php

namespace App\Filament\Resources\OwnerResource\Pages;

use App\Filament\Actions\DownloadExport;
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
            DownloadExport::make(OwnerExporter::class, 'propietarios'),
            Actions\CreateAction::make(),
        ];
    }
}
