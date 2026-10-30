<?php

namespace App\Filament\Resources\SurgeryResource\Pages;

use App\Filament\Actions\DownloadExport;
use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Exporters\SurgeryExporter;
use App\Filament\Resources\SurgeryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurgeries extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = SurgeryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(SurgeryExporter::class, 'cirugias', 'Cirugías'),
            Actions\CreateAction::make(),
        ];
    }
}
