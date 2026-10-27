<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Actions\DownloadExport;
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
            DownloadExport::make(VaccinationExporter::class, 'vacunaciones'),
            Actions\CreateAction::make(),
        ];
    }
}
