<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Autorización estándar para los Resources "clínicos" (Pet, Owner, Consultation,
 * Surgery, Test, Vaccination): todos los roles pueden ver; por defecto también
 * crear/editar; solo admin+veterinario pueden eliminar. Los Resources cuyo
 * create/edit debe restringirse a admin+veterinario sobrescriben createRoles()/editRoles().
 */
trait HasClinicResourceAuthorization
{
    protected static function viewRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected static function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected static function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT];
    }

    protected static function deleteRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(static::viewRoles()) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(static::createRoles()) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(static::editRoles()) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(static::deleteRoles()) ?? false;
    }
}
