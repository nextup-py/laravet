<?php

namespace App\Filament\Resources\NeighborhoodResource\Pages;

use App\Filament\Concerns\GuardsDeletion;
use App\Filament\Resources\NeighborhoodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNeighborhood extends EditRecord
{
    protected static string $resource = NeighborhoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            GuardsDeletion::apply(Actions\DeleteAction::make()),
        ];
    }
}
