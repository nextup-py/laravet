<?php

namespace App\Filament\Concerns;

/**
 * Versión para RelationManagers del patrón "solo admin".
 */
trait HasAdminOnlyRelationManagerAuthorization
{
    public function canViewAny(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }
}
