<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Filament\Resources\ProblemReportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageProblemReports extends ManageRecords
{
    protected static string $resource = ProblemReportResource::class;

    public function getTitle(): string
    {
        return 'Reportes de problemas';
    }
}
