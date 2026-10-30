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

    /**
     * Etiquetas en español de los roles base (el nombre guardado es el identificador en inglés).
     *
     * @var array<string, string>
     */
    private const LABELS = [
        self::ADMIN => 'Administrador',
        self::VETERINARIAN => 'Veterinario',
        self::ASSISTANT => 'Asistente',
    ];

    /**
     * Nombre que se muestra al personal: la etiqueta en español de un rol base o, en un rol
     * creado por un admin, el nombre tal cual lo escribió.
     */
    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? $name;
    }

    public static function isBase(string $name): bool
    {
        return in_array($name, self::all(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ADMIN, self::VETERINARIAN, self::ASSISTANT];
    }
}
