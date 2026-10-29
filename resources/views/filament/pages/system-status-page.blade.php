<x-filament-panels::page>
    <x-filament::section heading="Versión desplegada">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Versión</dt>
                <dd class="font-medium">{{ $version ?? 'No disponible (el servidor no tiene git)' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Último cambio de código</dt>
                <dd class="font-medium">{{ $lastChange?->translatedFormat('d/m/Y H:i') ?? 'No disponible' }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Cola de trabajos">
        @if ($queue === null)
            <p class="text-sm text-gray-500 dark:text-gray-400">La cola no usa la base de datos: no hay datos para mostrar.</p>
        @else
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Pendientes</dt>
                    <dd class="font-medium">{{ $queue['pending'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Fallidos</dt>
                    <dd @class(['font-medium', 'text-danger-600 dark:text-danger-400' => $queue['failed'] > 0])>{{ $queue['failed'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Pendiente más antiguo</dt>
                    <dd @class(['font-medium', 'text-warning-600 dark:text-warning-400' => ($queue['oldest_minutes'] ?? 0) > 10])>
                        {{ $queue['oldest_minutes'] === null ? 'Ninguno' : 'hace '.$queue['oldest_minutes'].' min' }}
                    </dd>
                </div>
            </dl>
            @if (($queue['oldest_minutes'] ?? 0) > 10)
                <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">Hay trabajos esperando hace más de 10 minutos: el proceso que envía los correos podría estar detenido.</p>
            @endif
        @endif
    </x-filament::section>

    <x-filament::section heading="Tareas programadas">
        <p class="text-sm">
            Última vez que corrió el programador:
            <strong>{{ $schedulerLastRun?->translatedFormat('d/m/Y H:i') ?? 'sin registro todavía' }}</strong>
        </p>
        @if ($schedulerStale)
            <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">No hay una corrida reciente (debería haber una por minuto). Si no se debe a un deploy reciente, el cron podría estar detenido y no saldrían los recordatorios ni los avisos.</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Correo">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Envío</dt>
                <dd class="font-medium">{{ $mail['mailer'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Remitente</dt>
                <dd class="font-medium">{{ $mail['from'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Las respuestas llegan a</dt>
                <dd class="font-medium">{{ $mail['reply_to'] ?? 'Sin configurar' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Correo de soporte</dt>
                <dd class="font-medium">{{ $mail['support'] }}</dd>
            </div>
        </dl>
        @foreach ($mail['warnings'] as $warning)
            <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">{{ $warning }}</p>
        @endforeach
    </x-filament::section>

    <x-filament::section heading="Entorno">
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            @foreach ($environment as $label => $value)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="font-medium">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>
</x-filament-panels::page>
