<?php

namespace App\Filament\Resources\ExportLogResource\Pages;

use App\Filament\Resources\ExportLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExportLogs extends ListRecords
{
    protected static string $resource = ExportLogResource::class;

    public function getTitle(): string
    {
        return 'Registro de exportaciones';
    }
}
