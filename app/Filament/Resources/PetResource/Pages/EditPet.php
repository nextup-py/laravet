<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Concerns\GuardsDeletion;
use App\Filament\Resources\PetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPet extends EditRecord
{
    protected static string $resource = PetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            GuardsDeletion::apply(Actions\DeleteAction::make()),
        ];
    }
}
