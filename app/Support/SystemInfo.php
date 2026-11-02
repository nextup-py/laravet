<?php

namespace App\Support;

use App\Settings\ClinicSettings;
use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

/**
 * Datos de solo lectura para «Ayuda → Estado del sistema» (solo administradores) y para
 * adjuntar la versión a los reportes de problemas. Nada de lo que devuelve es sensible:
 * no incluye claves, contraseñas ni valores del .env.
 */
final class SystemInfo
{
    /** Archivo del programador; se puede cambiar (por ejemplo en los tests). */
    public static ?string $heartbeatPath = null;

    /** Versión desplegada: etiqueta o commit de git; null si el servidor no tiene git. */
    public static function version(): ?string
    {
        return self::git(['describe', '--tags', '--always']);
    }

    /** Fecha del último cambio de código desplegado (commit más reciente). */
    public static function lastChange(): ?Carbon
    {
        $date = self::git(['log', '-1', '--format=%cI']);

        return $date ? Carbon::parse($date)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * @return array{pending: int, failed: int, oldest_minutes: ?int}|null null si la cola no usa tablas de base de datos
     */
    public static function queue(): ?array
    {
        if (! Schema::hasTable('jobs')) {
            return null;
        }

        // Solo cuenta lo que ya debería estar procesándose: ni los trabajos con retraso programado
        // ni los que el worker tiene reservados en este momento.
        $oldest = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->timestamp)
            ->min('available_at');

        return [
            'pending' => DB::table('jobs')->count(),
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'oldest_minutes' => $oldest ? (int) Carbon::createFromTimestamp($oldest)->diffInMinutes(now()) : null,
        ];
    }

    /**
     * Archivo donde el programador deja la hora de su última corrida (ver routes/console.php).
     * Es un archivo y no la caché a propósito: un `cache:clear` no debe borrar la marca ni
     * dar una falsa alarma de que el cron dejó de correr.
     */
    public static function heartbeatPath(): string
    {
        return self::$heartbeatPath ?? storage_path('framework/scheduler-heartbeat');
    }

    public static function recordSchedulerHeartbeat(): void
    {
        File::put(self::heartbeatPath(), now()->toIso8601String(), true);
    }

    /** Última vez que corrió el programador de tareas (cron); null si nunca dejó marca. */
    public static function schedulerLastRun(): ?Carbon
    {
        $value = is_file(self::heartbeatPath()) ? trim((string) file_get_contents(self::heartbeatPath())) : '';

        return $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
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
     * Cómo está configurado el servidor (solo etiquetas de estado, nunca valores del .env) y
     * qué conviene revisar: configuración en caché, modo debug, clave de IA y espacio en disco.
     *
     * @return array{items: array<string, string>, warnings: array<int, string>}
     */
    public static function runtime(): array
    {
        $warnings = [];
        $debug = (bool) config('app.debug');
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if ($debug && ! app()->environment(['local', 'testing'])) {
            $warnings[] = 'El modo debug está activo: ante un error se mostrarían detalles internos. Pon APP_DEBUG=false en el .env y vuelve a ejecutar php artisan config:cache.';
        }

        if ($free !== false && $total && ($free / $total < 0.1 || $free < 1024 ** 3)) {
            $warnings[] = 'Queda poco espacio libre en el disco del servidor.';
        }

        return [
            'items' => [
                'Cola' => (string) config('queue.default'),
                'Caché' => (string) config('cache.default'),
                'Sesiones' => (string) config('session.driver'),
                'Configuración en caché' => app()->configurationIsCached() ? 'Sí' : 'No',
                'Modo debug' => $debug ? 'Activo' : 'Desactivado',
                'Asistente de IA' => filled(config('services.anthropic.key')) ? 'Configurado' : 'Sin configurar',
                'Espacio libre en disco' => $free !== false && $total ? Number::fileSize($free).' de '.Number::fileSize($total) : 'No disponible',
            ],
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
        return Cache::remember('system-info:git:'.md5(implode(' ', $arguments)), 60, function () use ($arguments): ?string {
            try {
                $result = Process::path(base_path())->timeout(5)->run(['git', ...$arguments]);
            } catch (\Throwable) {
                return null;
            }

            return $result->successful() && filled(trim($result->output())) ? trim($result->output()) : null;
        });
    }
}
