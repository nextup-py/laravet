<?php

namespace App\Support;

use App\Models\Owner;
use App\Models\Pet;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PetSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos referenciales de las mascotas del demo: una imagen por especie y raza
 * (`canino-labrador-retriever.jpg`, `felino-srd.jpg`…) guardada en
 * `database/seeders/images/pets`. Solo se asignan a las 15 mascotas de `PetSeeder::PETS`
 * (se identifican por la CI del propietario demo y el nombre) o, con `$all`, a cualquier mascota
 * cuya especie y raza tengan imagen; salvo que se pida sobrescribir, solo si todavía no tienen
 * foto. Una raza puede tener variantes (`slug-2.jpg`…). Nunca crea ni borra mascotas.
 */
final class DemoPetPhotos
{
    /** Carpeta del disco público donde quedan las fotos copiadas. */
    public const DIRECTORY = 'demo-pets';

    /** Carpeta de origen; se puede cambiar (por ejemplo en los tests). */
    public static ?string $sourcePath = null;

    /** @var array<string, array<int, string>> */
    private static array $variantsCache = [];

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
     * Qué pasaría con cada mascota, sin escribir nada. Por defecto solo las 15 del demo del
     * seeder; con `$all` cualquier mascota cuya especie y raza tengan imagen.
     *
     * @return array<int, array{name: string, slug: string, file: ?string, status: string, pet: ?Pet}>
     */
    public static function plan(bool $overwrite = false, bool $all = false): array
    {
        return $all ? self::planAll($overwrite) : self::planSeederPets($overwrite);
    }

    /**
     * Copia las fotos al disco público y las asigna.
     *
     * @return array{assigned: int, skipped: int, refreshed: int, missing_images: array<int, string>}
     */
    public static function apply(bool $overwrite = false, bool $all = false): array
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk', 'public'));
        $refreshed = self::refreshCopiedFiles($disk);
        $assigned = 0;
        $skipped = 0;
        $missing = [];

        foreach (self::plan($overwrite, $all) as $row) {
            if ($row['status'] === self::STATUS_NO_IMAGE_FILE) {
                $missing[$row['slug']] = $row['slug'];
            }

            if ($row['status'] !== self::STATUS_ASSIGN) {
                $skipped++;

                continue;
            }

            $path = self::DIRECTORY.'/'.basename($row['file']);

            if (! $disk->exists($path)) {
                $disk->put($path, (string) file_get_contents($row['file']));
            }

            $row['pet']->forceFill(['image' => $path])->saveQuietly();
            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped, 'refreshed' => $refreshed, 'missing_images' => array_values($missing)];
    }

    /**
     * Si una imagen del repositorio cambió (por ejemplo, se reemplazó una foto), actualiza la copia
     * que ya está en el disco. Las mascotas siguen apuntando al mismo archivo, así que la ven nueva
     * sin tocar `pets.image`.
     */
    private static function refreshCopiedFiles(Filesystem $disk): int
    {
        $refreshed = 0;

        foreach (glob(self::sourcePath().'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            $path = self::DIRECTORY.'/'.basename($file);

            if ($disk->exists($path) && md5((string) $disk->get($path)) !== md5_file($file)) {
                $disk->put($path, (string) file_get_contents($file));
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * @return array<int, array{name: string, slug: string, file: ?string, status: string, pet: ?Pet}>
     */
    private static function planSeederPets(bool $overwrite): array
    {
        $owners = Owner::query()->whereIn('ci', array_column(OwnerSeeder::OWNERS, 'ci'))->get()->keyBy('ci');
        $rows = [];

        foreach (PetSeeder::PETS as [$name, $species, $breed, , , , , , , $ownerIndex]) {
            $ci = OwnerSeeder::OWNERS[$ownerIndex % count(OwnerSeeder::OWNERS)]['ci'];
            $owner = $owners->get($ci);
            $pet = $owner ? Pet::query()->where('owner_id', $owner->id)->where('name', $name)->first() : null;

            $rows[] = self::row($name, self::slug($species, $breed), $pet, $overwrite);
        }

        return $rows;
    }

    /**
     * @return array<int, array{name: string, slug: string, file: ?string, status: string, pet: ?Pet}>
     */
    private static function planAll(bool $overwrite): array
    {
        $rows = [];

        foreach (Pet::query()->orderBy('id')->cursor() as $pet) {
            $rows[] = self::row($pet->name, self::slug($pet->species->value, (string) $pet->breed), $pet, $overwrite);
        }

        return $rows;
    }

    /**
     * @return array{name: string, slug: string, file: ?string, status: string, pet: ?Pet}
     */
    private static function row(string $name, string $slug, ?Pet $pet, bool $overwrite): array
    {
        $file = $pet ? self::pickImage($slug, $pet->id) : null;

        $status = match (true) {
            $pet === null => self::STATUS_NOT_FOUND,
            $file === null => self::STATUS_NO_IMAGE_FILE,
            filled($pet->image) && ! $overwrite => self::STATUS_HAS_PHOTO,
            default => self::STATUS_ASSIGN,
        };

        return ['name' => $name, 'slug' => $slug, 'file' => $file, 'status' => $status, 'pet' => $pet];
    }

    /**
     * Variantes de una raza: `slug.jpg`, `slug-2.jpg`, `slug-3.jpg`… Cada mascota usa siempre la
     * misma según su id, así volver a correr el comando no cambia las fotos ya asignadas.
     */
    private static function pickImage(string $slug, int $petId): ?string
    {
        $variants = self::variants($slug);

        return $variants === [] ? null : $variants[$petId % count($variants)];
    }

    /**
     * @return array<int, string>
     */
    private static function variants(string $slug): array
    {
        $key = self::sourcePath().'|'.$slug;

        if (! isset(self::$variantsCache[$key])) {
            $files = glob(self::sourcePath().'/'.$slug.'{,-[0-9]}.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
            sort($files);
            self::$variantsCache[$key] = array_values($files);
        }

        return self::$variantsCache[$key];
    }
}
