<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
