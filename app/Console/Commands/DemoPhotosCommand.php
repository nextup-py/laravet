<?php

namespace App\Console\Commands;

use App\Support\DemoPetPhotos;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Asigna las fotos referenciales a las 15 mascotas del demo, también en una instancia con
 * APP_ENV=production que se usa como demo (donde el DemoSeeder se niega a correr). Solo toca
 * la columna `image` de esas mascotas y únicamente si no tienen foto.
 */
class DemoPhotosCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'laravet:demo-photos
        {--dry-run : Muestra qué mascotas recibirían foto, sin escribir nada}
        {--overwrite : También reemplaza la foto de las mascotas demo que ya tienen una}
        {--force : No pide confirmación en producción}';

    protected $description = 'Asigna fotos referenciales (por especie y raza) a las mascotas del demo.';

    public function handle(): int
    {
        $overwrite = (bool) $this->option('overwrite');
        $plan = DemoPetPhotos::plan($overwrite);

        $this->table(
            ['Mascota', 'Imagen', 'Qué pasará'],
            array_map(fn (array $row): array => [$row['name'], $row['slug'], $this->statusLabel($row['status'])], $plan),
        );

        if ($this->option('dry-run')) {
            $this->info('Simulación: no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('Vas a asignar fotos a las mascotas demo de una instancia en producción.')) {
            return self::FAILURE;
        }

        $result = DemoPetPhotos::apply($overwrite);

        $this->info("Fotos asignadas: {$result['assigned']}. Sin cambios: {$result['skipped']}.");

        if ($result['missing_images'] !== []) {
            $this->warn('Faltan imágenes en database/seeders/images/pets para: '.implode(', ', $result['missing_images']).'.');
        }

        return self::SUCCESS;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            DemoPetPhotos::STATUS_ASSIGN => 'Recibe foto',
            DemoPetPhotos::STATUS_HAS_PHOTO => 'Ya tiene foto (se conserva)',
            DemoPetPhotos::STATUS_NO_IMAGE_FILE => 'Falta el archivo de imagen',
            default => 'No existe esta mascota demo',
        };
    }
}
