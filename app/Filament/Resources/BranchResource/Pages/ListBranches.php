<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBranches extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
