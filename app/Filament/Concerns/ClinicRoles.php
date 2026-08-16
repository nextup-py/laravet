<?php

namespace App\Filament\Concerns;

/**
 * Nombres de los roles de Spatie Permission usados en todo el panel de Filament.
 * Centraliza los strings de rol para no repetirlos en cada Resource/RelationManager.
 */
final class ClinicRoles
{
    public const ADMIN = 'admin';

    public const VETERINARIAN = 'veterinarian';

    public const ASSISTANT = 'assistant';

    /** Roles con acceso a funciones clínicas sensibles (ej. diagnóstico asistido por IA). */
    public const CLINICAL_STAFF = [self::ADMIN, self::VETERINARIAN];

    /** Todos los roles con acceso al panel. */
    public const ALL_STAFF = [self::ADMIN, self::VETERINARIAN, self::ASSISTANT];
}
