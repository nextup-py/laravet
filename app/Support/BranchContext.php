<?php

namespace App\Support;

use App\Models\Branch;

/**
 * Sucursal "activa" de la sesión actual: la que se anota en los registros nuevos.
 *
 * - El personal usa la sucursal de su usuario.
 * - El administrador no tiene sucursal propia: usa la que eligió en el panel (guardada en sesión) y,
 *   si no eligió ninguna, la sucursal principal.
 * - Fuera de una sesión (consola, colas, seeders) se usa la sucursal principal.
 */
class BranchContext
{
    /** Clave de sesión donde el administrador guarda la sucursal que eligió. */
    public const SESSION_KEY = 'active_branch_id';

    /** Id de la sucursal activa, o null solo si no existe ninguna sucursal (base sin migrar). */
    public static function currentId(): ?int
    {
        $user = auth()->user();

        if ($user?->branch_id) {
            return (int) $user->branch_id;
        }

        $chosen = $user ? session()->get(self::SESSION_KEY) : null;

        // Se valida cada vez: la sucursal elegida pudo deshabilitarse o borrarse después.
        if ($chosen && Branch::query()->active()->whereKey($chosen)->exists()) {
            return (int) $chosen;
        }

        return Branch::main()?->id;
    }

    public static function current(): ?Branch
    {
        $id = self::currentId();

        return $id ? Branch::query()->find($id) : null;
    }

    /**
     * El administrador elige la sucursal activa. Solo se admiten sucursales habilitadas.
     */
    public static function switchTo(Branch|int $branch): void
    {
        $id = $branch instanceof Branch ? $branch->id : $branch;

        abort_unless(Branch::query()->active()->whereKey($id)->exists(), 404);

        session()->put(self::SESSION_KEY, $id);
    }

    /** Vuelve a la sucursal principal (olvida la elección). */
    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
