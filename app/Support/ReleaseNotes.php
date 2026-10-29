<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Novedades del sistema para el personal: un archivo Markdown por entrada en
 * `resources/novedades/AAAA-MM-DD-slug.md`, con encabezado `---` (title). La fecha sale del
 * nombre del archivo. Se escriben en lenguaje simple («ahora puedes…») y se agrega una
 * entrada con cada cambio visible para el usuario (ver CLAUDE.md).
 */
final class ReleaseNotes
{
    /**
     * Entradas de la más nueva a la más vieja.
     *
     * @return Collection<string, array{slug: string, title: string, date: Carbon, body: string}>
     */
    public static function all(): Collection
    {
        return collect(File::glob(resource_path('novedades/*.md')))
            ->map(fn (string $path): array => self::parse($path))
            ->sortByDesc(fn (array $entry): string => $entry['date']->format('Y-m-d').'|'.$entry['slug'])
            ->keyBy('slug');
    }

    /**
     * @return array{slug: string, title: string, date: Carbon, body: string}
     */
    private static function parse(string $path): array
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $name, $parts);
        ['meta' => $meta, 'body' => $body] = FrontMatter::parse(File::get($path));

        return [
            'slug' => $parts[2] ?? $name,
            'title' => $meta['title'] ?? Str::headline($parts[2] ?? $name),
            'date' => Carbon::parse($parts[1] ?? File::lastModified($path)),
            'body' => $body,
        ];
    }
}
