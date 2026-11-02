<?php

namespace App\Support;

use App\Models\Owner;
use App\Models\Pet;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PetSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos referenciales de las mascotas del demo: una imagen por especie y raza
 * (`canino-labrador-retriever.jpg`, `felino-srd.jpg`…) guardada en
 * `database/seeders/images/pets`. Solo se asignan a las 15 mascotas de `PetSeeder::PETS`
 * (se identifican por la CI del propietario demo y el nombre) y, salvo que se pida
 * sobrescribir, solo si todavía no tienen foto. Nunca crea ni borra mascotas.
 */
final class DemoPetPhotos
{
    /** Carpeta del disco público donde quedan las fotos copiadas. */
    public const DIRECTORY = 'demo-pets';

    /** Carpeta de origen; se puede cambiar (por ejemplo en los tests). */
    public static ?string $sourcePath = null;

    public const STATUS_ASSIGN = 'assign';

    public const STATUS_HAS_PHOTO = 'has_photo';

    public const STATUS_NO_IMAGE_FILE = 'no_image_file';

    public const STATUS_NOT_FOUND = 'not_found';

    public static function sourcePath(): string
    {
        return self::$sourcePath ?? database_path('seeders/images/pets');
    }

    public static function slug(string $species, string $breed): string
    {
        return Str::slug($species.' '.$breed);
    }

    /**
     * Qué pasaría con cada mascota demo, sin escribir nada.
     *
     * @return array<int, array{name: string, slug: string, file: ?string, status: string, pet: ?Pet}>
     */
    public static function plan(bool $overwrite = false): array
    {
        $owners = Owner::query()->whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci'))->get()->keyBy('ci');
        $rows = [];

        foreach (PetSeeder::PETS as [$name, $species, $breed, , , , , , , $ownerIndex]) {
            $ci = OwnerSeeder::OWNERS[$ownerIndex % count(OwnerSeeder::OWNERS)]['ci'];
            $owner = $owners->get($ci);
            $pet = $owner ? Pet::query()->where('owner_id', $owner->id)->where('name', $name)->first() : null;
            $slug = self::slug($species, $breed);
            $file = self::findImage($slug);

            $status = match (true) {
                $pet === null => self::STATUS_NOT_FOUND,
                $file === null => self::STATUS_NO_IMAGE_FILE,
                filled($pet->image) && ! $overwrite => self::STATUS_HAS_PHOTO,
                default => self::STATUS_ASSIGN,
            };

            $rows[] = ['name' => $name, 'slug' => $slug, 'file' => $file, 'status' => $status, 'pet' => $pet];
        }

        return $rows;
    }

    /**
     * Copia las fotos al disco público y las asigna a las mascotas demo.
     *
     * @return array{assigned: int, skipped: int, missing_images: array<int, string>}
     */
    public static function apply(bool $overwrite = false): array
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk', 'public'));
        $assigned = 0;
        $skipped = 0;
        $missing = [];

        foreach (self::plan($overwrite) as $row) {
            if ($row['status'] === self::STATUS_NO_IMAGE_FILE) {
                $missing[$row['slug']] = $row['slug'];
            }

            if ($row['status'] !== self::STATUS_ASSIGN) {
                $skipped++;

                continue;
            }

            $path = self::DIRECTORY.'/'.$row['slug'].'.'.pathinfo($row['file'], PATHINFO_EXTENSION);
            $disk->put($path, (string) file_get_contents($row['file']));

            $row['pet']->forceFill(['image' => $path])->saveQuietly();
            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped, 'missing_images' => array_values($missing)];
    }

    private static function findImage(string $slug): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $path = self::sourcePath().'/'.$slug.'.'.$extension;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
