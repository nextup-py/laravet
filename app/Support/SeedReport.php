<?php

namespace App\Support;

use App\Filament\Concerns\ClinicRoles;
use Illuminate\Console\Command;

/**
 * Junta lo que los seeders crean o actualizan (usuarios y contraseñas) para mostrarlo en una sola
 * tabla al final, en vez de perder una contraseña "que no se vuelve a mostrar" entre líneas de
 * progreso. Es un singleton: los seeders anotan y quien inició la siembra la imprime.
 */
class SeedReport
{
    /** @var array<string, array{email: string, role: string, password: ?string}> */
    private array $users = [];

    /**
     * @param  ?string  $password  Contraseña en claro si se fijó o generó en esta corrida; null si no cambió.
     */
    public function user(string $email, string $role, ?string $password): void
    {
        $this->users[$email] = ['email' => $email, 'role' => $role, 'password' => $password];
    }

    /**
     * @return array<int, array{email: string, role: string, password: ?string}>
     */
    public function users(): array
    {
        return array_values($this->users);
    }

    /**
     * Imprime la tabla de usuarios y vacía el registro; no hace nada si no hubo usuarios.
     */
    public function render(?Command $command): void
    {
        if ($command === null || $this->users === []) {
            $this->users = [];

            return;
        }

        $command->newLine();
        $command->line('<options=bold>Usuarios para entrar al panel</>');
        $command->table(
            ['Correo', 'Rol', 'Contraseña'],
            array_map(fn (array $user): array => [
                $user['email'],
                ClinicRoles::label($user['role']) === $user['role'] ? $user['role'] : ClinicRoles::label($user['role'])." ({$user['role']})",
                $user['password'] ?? '(sin cambios)',
            ], $this->users()),
        );

        if (collect($this->users)->contains(fn (array $user): bool => $user['password'] !== null && $user['password'] !== 'password')) {
            $command->warn('Guarda las contraseñas generadas ahora: no se vuelven a mostrar.');
        }

        $this->users = [];
    }
}
