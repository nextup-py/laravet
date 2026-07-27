<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $roleName = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roleName = $data['role'] ?? null;
        unset($data['role']);
        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->roleName) {
            $this->record->syncRoles([$this->roleName]);
        }
    }
}
