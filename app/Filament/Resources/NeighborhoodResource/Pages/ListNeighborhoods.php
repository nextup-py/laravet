<?php

namespace App\Filament\Resources\NeighborhoodResource\Pages;

use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Resources\NeighborhoodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNeighborhoods extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = NeighborhoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
