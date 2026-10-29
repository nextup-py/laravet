<?php

namespace App\Support;

/**
 * Enlace de WhatsApp (wa.me) a partir de un teléfono, normalizado al prefijo 595 de Paraguay.
 */
final class WhatsappLink
{
    public static function url(string $phone, ?string $text = null): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $digits = str_starts_with($digits, '595') ? $digits : '595'.ltrim($digits, '0');

        return 'https://wa.me/'.$digits.(filled($text) ? '?text='.rawurlencode($text) : '');
    }
}
