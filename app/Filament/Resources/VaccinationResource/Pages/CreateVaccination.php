<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Resources\VaccinationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateVaccination extends CreateRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}
