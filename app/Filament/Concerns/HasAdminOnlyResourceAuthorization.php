<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Autorización para Resources exclusivamente administrativos (User, City,
 * Department, Neighborhood): toda acción requiere el rol admin.
 */
trait HasAdminOnlyResourceAuthorization
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }
}
