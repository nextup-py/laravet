<?php

namespace App\Filament\Concerns;

/**
 * Nombres de los roles por defecto sembrados por RolesAndPermissionsSeeder.
 * Los permisos reales (qué puede hacer cada rol) viven en la base de datos,
 * gestionables desde "Configuración → Roles y permisos" — ver CLAUDE.md.
 */
final class ClinicRoles
{
    public const ADMIN = 'admin';

    public const VETERINARIAN = 'veterinarian';

    public const ASSISTANT = 'assistant';
}
