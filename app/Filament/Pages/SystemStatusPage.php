<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ClinicRoles;
use App\Support\SystemInfo;
use Filament\Pages\Page;

/**
 * Estado del sistema (solo administradores): versión desplegada, cola, tareas programadas,
 * correo y versiones. Es de solo lectura y no muestra claves ni valores del .env (ver SystemInfo).
 * Como «Datos de la clínica», es un chequeo de rol y no un permiso de Shield.
 */
class SystemStatusPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Estado del sistema';

    protected static ?string $title = 'Estado del sistema';

    protected static ?string $slug = 'estado-del-sistema';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.system-status-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(ClinicRoles::ADMIN) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $schedulerLastRun = SystemInfo::schedulerLastRun();

        return [
            'version' => SystemInfo::version(),
            'lastChange' => SystemInfo::lastChange(),
            'queue' => SystemInfo::queue(),
            'schedulerLastRun' => $schedulerLastRun,
            // El cron corre cada minuto: más de 5 sin marca es señal de que dejó de funcionar.
            'schedulerStale' => ! $schedulerLastRun || $schedulerLastRun->diffInMinutes(now()) > 5,
            'mail' => SystemInfo::mail(),
            'environment' => SystemInfo::environment(),
        ];
    }
}
