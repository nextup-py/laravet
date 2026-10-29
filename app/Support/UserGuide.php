<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Guía de uso del panel: temas escritos en Markdown en `resources/guia/*.md`,
 * cada uno con un encabezado `---` (title, order, icon y, opcional, permission).
 * El usuario solo ve los temas cuyo permiso tiene, así que un rol sin acceso a
 * cirugías no lee la guía de cirugías. Al cambiar el sistema se actualiza el
 * tema correspondiente (ver CLAUDE.md).
 */
final class UserGuide
{
    /**
     * Temas visibles para el usuario, ordenados.
     *
     * @return Collection<string, array{slug: string, title: string, icon: string, order: int, permission: ?string, body: string}>
     */
    public static function topicsFor(?User $user): Collection
    {
        return self::all()
            ->filter(fn (array $topic): bool => $topic['permission'] === null || ($user?->can($topic['permission']) ?? false));
    }

    /**
     * Todos los temas de la guía, sin filtrar por permisos.
     *
     * @return Collection<string, array{slug: string, title: string, icon: string, order: int, permission: ?string, body: string}>
     */
    public static function all(): Collection
    {
        return collect(File::glob(resource_path('guia/*.md')))
            ->map(fn (string $path): array => self::parse($path))
            ->sortBy('order')
            ->keyBy('slug');
    }

    public static function toHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @return array{slug: string, title: string, icon: string, order: int, permission: ?string, body: string}
     */
    private static function parse(string $path): array
    {
        // El prefijo numérico del archivo solo ordena el directorio; no forma parte de la URL.
        $slug = preg_replace('/^\d+-/', '', pathinfo($path, PATHINFO_FILENAME));
        ['meta' => $meta, 'body' => $body] = FrontMatter::parse(File::get($path));

        return [
            'slug' => $slug,
            'title' => $meta['title'] ?? Str::headline($slug),
            'icon' => $meta['icon'] ?? 'heroicon-o-book-open',
            'order' => (int) ($meta['order'] ?? 100),
            'permission' => ($meta['permission'] ?? '') !== '' ? $meta['permission'] : null,
            'body' => $body,
        ];
    }
}
