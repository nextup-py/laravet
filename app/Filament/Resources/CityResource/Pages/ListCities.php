<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Resources\CityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
