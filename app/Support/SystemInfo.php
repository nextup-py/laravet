<?php

namespace App\Support;

use App\Settings\ClinicSettings;
use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de solo lectura para «Ayuda → Estado del sistema» (solo administradores) y para
 * adjuntar la versión a los reportes de problemas. Nada de lo que devuelve es sensible:
 * no incluye claves, contraseñas ni valores del .env.
 */
final class SystemInfo
{
    /** Clave de caché donde el programador deja la hora de su última corrida (ver routes/console.php). */
    public const SCHEDULER_HEARTBEAT = 'scheduler:last_run';

    /** Versión desplegada: etiqueta o commit de git; null si el servidor no tiene git. */
    public static function version(): ?string
    {
        return self::git(['describe', '--tags', '--always']);
    }

    /** Fecha del último cambio de código desplegado (commit más reciente). */
    public static function lastChange(): ?Carbon
    {
        $date = self::git(['log', '-1', '--format=%cI']);

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * @return array{pending: int, failed: int, oldest_minutes: ?int}|null null si la cola no usa tablas de base de datos
     */
    public static function queue(): ?array
    {
        if (! Schema::hasTable('jobs')) {
            return null;
        }

        $oldest = DB::table('jobs')->min('created_at');

        return [
            'pending' => DB::table('jobs')->count(),
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'oldest_minutes' => $oldest ? (int) Carbon::createFromTimestamp($oldest)->diffInMinutes(now()) : null,
        ];
    }

    /** Última vez que corrió el programador de tareas (cron); null si nunca dejó marca. */
    public static function schedulerLastRun(): ?Carbon
    {
        $value = Cache::get(self::SCHEDULER_HEARTBEAT);

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Cómo salen hoy los correos y qué falta para que los recordatorios funcionen.
     *
     * @return array{mailer: string, from: string, reply_to: ?string, support: string, warnings: array<int, string>}
     */
    public static function mail(): array
    {
        $clinic = ClinicSettings::tryGet();
        $warnings = [];

        if (blank($clinic?->email)) {
            $warnings[] = 'Falta el email de la clínica: no se envían recordatorios a propietarios y las respuestas no tienen a dónde llegar.';
        }

        if ($clinic && ! ClinicMail::isAllowedAddress($clinic->mail_from_address)) {
            $warnings[] = 'La dirección de envío guardada no es válida: se usa la del servidor como respaldo.';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $warnings[] = 'El servidor no envía correos reales (el mailer es «'.config('mail.default').'»).';
        }

        return [
            'mailer' => (string) config('mail.default'),
            'from' => ClinicMail::fromAddress($clinic),
            'reply_to' => filled($clinic?->email) ? $clinic->email : null,
            'support' => $clinic?->support_email ?: ClinicSettings::DEFAULT_SUPPORT_EMAIL,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function environment(): array
    {
        return [
            'Entorno' => (string) app()->environment(),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Filament' => InstalledVersions::getPrettyVersion('filament/filament') ?? '—',
            'Base de datos' => DB::getDriverName().' '.DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'Zona horaria' => (string) config('app.timezone'),
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private static function git(array $arguments): ?string
    {
        return Cache::remember('system-info:git:'.md5(implode(' ', $arguments)), 600, function () use ($arguments): ?string {
            try {
                $result = Process::path(base_path())->timeout(5)->run(['git', ...$arguments]);
            } catch (\Throwable) {
                return null;
            }

            return $result->successful() && filled(trim($result->output())) ? trim($result->output()) : null;
        });
    }
}
