<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Resources\UserResource;
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

        // El administrador no tiene sucursal propia: elige una activa en la barra superior.
        if ($this->roleName === ClinicRoles::ADMIN) {
            $data['branch_id'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->roleName) {
            $this->record->syncRoles([$this->roleName]);
        }
    }
}
