<?php

namespace App\Filament\Resources\NeighborhoodResource\Pages;

use App\Filament\Resources\NeighborhoodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNeighborhood extends CreateRecord
{
    protected static string $resource = NeighborhoodResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Neighborhood registered');
    }
}
