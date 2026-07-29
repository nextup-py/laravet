<?php

namespace App\Filament\Concerns;

/**
 * Igual que HasClinicResourceAuthorization pero para RelationManagers, donde
 * Filament invoca métodos de instancia (canEditAny/canDeleteAny en vez de
 * canEdit/canDelete). Ver esa clase para el criterio de roles por defecto.
 */
trait HasClinicRelationManagerAuthorization
{
    protected function viewRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected function deleteRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    public function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole($this->viewRoles()) ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole($this->createRoles()) ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasAnyRole($this->editRoles()) ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole($this->deleteRoles()) ?? false;
    }
}
