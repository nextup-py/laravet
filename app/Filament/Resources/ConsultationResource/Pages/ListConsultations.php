<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Actions\DownloadExport;
use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Exporters\ConsultationExporter;
use App\Filament\Resources\ConsultationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsultations extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = ConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(ConsultationExporter::class, 'consultas', 'Consultas'),
            Actions\CreateAction::make(),
        ];
    }
}
