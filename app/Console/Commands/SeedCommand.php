<?php

namespace App\Console\Commands;

use App\Support\DemoDataReset;
use App\Support\SeedReport;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoVolumeSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class SeedCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'laravet:seed
        {--base : Siembra roles y permisos, regiones de Paraguay y el usuario admin}
        {--demo : Siembra (o actualiza) los datos demo; vuelve a calcular las fechas relativas a hoy}
        {--volume= : Agrega mascotas de prueba hasta llegar a esta cantidad (sin valor usa DEMO_VOLUME)}
        {--reset-demo : Borra solo los datos demo, sin tocar los de la clínica}
        {--force : No pide confirmación (producción o borrado de datos demo)}';

    protected $description = 'Siembra la base de datos de forma guiada: base del sistema, datos demo, volumen de prueba o borrado de los datos demo.';

    public function handle(): int
    {
        $production = app()->environment('production');
        $actions = $this->requestedActions();

        if ($actions === null) {
            $actions = $this->input->isInteractive()
                ? $this->askActions($production)
                : ['default'];
        }

        if ($actions === []) {
            $this->warn('No elegiste nada para sembrar.');

            return self::SUCCESS;
        }

        if ($production && array_intersect($actions, ['demo', 'volume', 'reset'])) {
            $this->error('Los datos demo no se pueden sembrar ni borrar en producción.');

            return self::FAILURE;
        }

        if (in_array('volume', $actions, true)) {
            $this->applyVolume();
        }

        if (in_array('reset', $actions, true) && ! $this->confirmReset()) {
            $this->warn('Se canceló el borrado de los datos demo.');

            return self::FAILURE;
        }

        if ($production && ! $this->confirmToProceed('Estás sembrando la base de datos de producción.')) {
            return self::FAILURE;
        }

        $this->runActions($actions);

        app(SeedReport::class)->render($this);
        $this->nextSteps($actions);

        return self::SUCCESS;
    }

    /**
     * Acciones pedidas con opciones; null si no se pidió ninguna.
     *
     * @return ?array<int, string>
     */
    private function requestedActions(): ?array
    {
        $actions = array_keys(array_filter([
            'reset' => $this->option('reset-demo'),
            'base' => $this->option('base'),
            'demo' => $this->option('demo'),
            'volume' => $this->input->hasParameterOption('--volume'),
        ]));

        return $actions === [] ? null : $actions;
    }

    /**
     * @return array<int, string>
     */
    private function askActions(bool $production): array
    {
        $this->info('Siembra de datos de Laravet');
        $this->line('Todo lo que se siembra es repetible: volver a ejecutarlo actualiza, no duplica.');
        $this->newLine();

        $options = [
            'base' => 'Base del sistema: roles y permisos, regiones de Paraguay y usuario admin',
        ];

        if (! $production) {
            $options += [
                'demo' => 'Datos demo: personal, 10 propietarios, 15 mascotas e historial (refresca las fechas a hoy)',
                'volume' => 'Volumen de prueba: muchas mascotas extra para probar paginación, filtros y exportación',
                'reset' => 'Borrar los datos demo (no toca los datos de la clínica)',
            ];
        }

        $chosen = multiselect(
            label: '¿Qué quieres sembrar?',
            options: $options,
            default: $production ? ['base'] : ['base', 'demo'],
            hint: $production
                ? 'En producción solo se siembra la base del sistema.'
                : 'Espacio para marcar, Enter para continuar. Los pasos que faltan se siembran solos.',
        );

        if (in_array('volume', $chosen, true)) {
            $amount = text(
                label: '¿Cuántas mascotas de volumen quieres tener en total?',
                default: (string) config('app.demo_volume'),
                validate: fn (string $value): ?string => ctype_digit($value) && (int) $value > 0 ? null : 'Escribe un número entero mayor que cero.',
                hint: 'Si ya hay algunas, solo se agregan las que faltan.',
            );

            config(['app.demo_volume' => (int) $amount]);
        }

        return $chosen;
    }

    private function applyVolume(): void
    {
        $value = $this->option('volume');

        if ($value === null) {
            return;
        }

        if (! ctype_digit((string) $value) || (int) $value < 1) {
            $this->fail('--volume necesita un número entero mayor que cero (por ejemplo --volume=50).');
        }

        config(['app.demo_volume' => (int) $value]);
    }

    private function confirmReset(): bool
    {
        $preview = (new DemoDataReset)->preview();

        $this->warn(sprintf(
            'Se borrarán %d propietarios demo con sus %d mascotas e historial, y hasta %d usuarios demo.',
            $preview['owners'], $preview['pets'], $preview['users'],
        ));

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Para borrar los datos demo sin preguntar usa --force.');

            return false;
        }

        return confirm('¿Borrar estos datos demo?', default: false);
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function runActions(array $actions): void
    {
        if (in_array('default', $actions, true)) {
            $this->seeder(DatabaseSeeder::class, 'Sembrando según el entorno');

            return;
        }

        if (in_array('reset', $actions, true)) {
            $this->components->task('Borrando los datos demo', function (): void {
                $result = (new DemoDataReset)->run();

                foreach ($result['kept_users'] as $email) {
                    $this->components->warn("Se conserva {$email}: tiene registros que no son del demo.");
                }
            });
        }

        if (in_array('base', $actions, true)) {
            $this->seeder(ProductionSeeder::class, 'Sembrando la base del sistema');
        }

        if (in_array('demo', $actions, true)) {
            $this->seeder(DemoSeeder::class, 'Sembrando los datos demo');
        }

        if (in_array('volume', $actions, true)) {
            $this->seeder(DemoVolumeSeeder::class, 'Sembrando el volumen de prueba ('.config('app.demo_volume').' mascotas)');
        }
    }

    /**
     * @param  class-string<Seeder>  $class
     */
    private function seeder(string $class, string $title): void
    {
        $this->newLine();
        $this->components->info($title);

        $this->call('db:seed', ['--class' => $class, '--force' => true]);
    }

    /**
     * @param  array<int, string>  $actions
     */
    private function nextSteps(array $actions): void
    {
        $this->newLine();
        $this->components->info('Listo.');

        if (array_intersect($actions, ['demo', 'volume', 'default'])) {
            $this->line('Entra al panel en <fg=cyan>'.config('app.url').'</> (con <fg=cyan>composer run dev</> en desarrollo).');
            $this->line('Para refrescar las fechas del demo vuelve a ejecutar <fg=cyan>php artisan laravet:seed --demo</>.');
        }
    }
}
