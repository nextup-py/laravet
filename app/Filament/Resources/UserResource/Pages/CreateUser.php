<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $roleName = null;

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('User registered');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleName = $data['role'] ?? null;
        unset($data['role']);
        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->roleName) {
            $this->record->syncRoles([$this->roleName]);
        }
    }
}
