<?php

namespace App\Console\Commands;

use App\Support\DemoPetPhotos;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Asigna las fotos referenciales a las mascotas del demo, también en una instancia con
 * APP_ENV=production que se usa como demo (donde el DemoSeeder se niega a correr). Solo toca
 * la columna `image` y únicamente en las mascotas que no tienen foto. Por defecto son las 15
 * del DemoSeeder; con --all, cualquier mascota cuya especie y raza tengan imagen.
 */
class DemoPhotosCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'laravet:demo-photos
        {--all : Todas las mascotas sin foto cuya especie y raza tengan imagen, no solo las 15 del DemoSeeder}
        {--dry-run : Muestra qué mascotas recibirían foto, sin escribir nada}
        {--overwrite : También reemplaza la foto de las mascotas que ya tienen una}
        {--force : No pide confirmación en producción}';

    protected $description = 'Asigna fotos referenciales (por especie y raza) a las mascotas del demo.';

    public function handle(): int
    {
        $overwrite = (bool) $this->option('overwrite');
        $all = (bool) $this->option('all');
        $plan = DemoPetPhotos::plan($overwrite, $all);

        $all ? $this->summary($plan) : $this->perPet($plan);

        if ($this->option('dry-run')) {
            $this->info('Simulación: no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed('Vas a asignar fotos a mascotas de una instancia en producción.')) {
            return self::FAILURE;
        }

        $result = DemoPetPhotos::apply($overwrite, $all);

        $this->info("Fotos asignadas: {$result['assigned']}. Sin cambios: {$result['skipped']}.");

        if ($result['refreshed'] > 0) {
            $this->info("Archivos de foto actualizados: {$result['refreshed']}.");
        }

        if ($result['missing_images'] !== []) {
            $this->warn('Faltan imágenes en database/seeders/images/pets para: '.implode(', ', $result['missing_images']).'.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{name: string, slug: string, file: ?string, status: string, pet: mixed}>  $plan
     */
    private function perPet(array $plan): void
    {
        $this->table(
            ['Mascota', 'Imagen', 'Qué pasará'],
            array_map(fn (array $row): array => [$row['name'], $row['slug'], $this->statusLabel($row['status'])], $plan),
        );
    }

    /**
     * Con muchas mascotas, una fila por especie y raza.
     *
     * @param  array<int, array{name: string, slug: string, file: ?string, status: string, pet: mixed}>  $plan
     */
    private function summary(array $plan): void
    {
        $groups = [];

        foreach ($plan as $row) {
            $groups[$row['slug']][$row['status']] = ($groups[$row['slug']][$row['status']] ?? 0) + 1;
        }

        ksort($groups);

        $this->table(
            ['Especie y raza', 'Recibirán foto', 'Ya tienen foto', 'Sin imagen'],
            array_map(fn (string $slug, array $counts): array => [
                $slug,
                $counts[DemoPetPhotos::STATUS_ASSIGN] ?? 0,
                $counts[DemoPetPhotos::STATUS_HAS_PHOTO] ?? 0,
                $counts[DemoPetPhotos::STATUS_NO_IMAGE_FILE] ?? 0,
            ], array_keys($groups), $groups),
        );

        $this->line('Total de mascotas: '.count($plan).'. Recibirán foto: '.count(array_filter($plan, fn (array $row): bool => $row['status'] === DemoPetPhotos::STATUS_ASSIGN)).'.');
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
