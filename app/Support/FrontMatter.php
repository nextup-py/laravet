<?php

namespace App\Support;

/**
 * Separa el encabezado `---` (líneas `clave: valor`) del cuerpo de un archivo Markdown.
 * Lo usan la guía de uso y las novedades del sistema.
 */
final class FrontMatter
{
    /**
     * @return array{meta: array<string, string>, body: string}
     */
    public static function parse(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        $meta = [];
        $body = $content;

        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $content, $matches)) {
            foreach (explode("\n", $matches[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $meta[trim($key)] = trim($value);
                }
            }

            $body = $matches[2];
        }

        return ['meta' => $meta, 'body' => trim($body)];
    }
}
