<?php

namespace App\Support;

use App\Settings\ClinicSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Identidad de los correos que salen del sistema: el remitente muestra el nombre de la
 * clínica, la dirección de envío es la que la clínica eligió en «Datos de la clínica»
 * (o MAIL_FROM_ADDRESS, y siempre de un dominio verificado en el proveedor) y las
 * respuestas van al email de la clínica. Se lee en cada envío, así un cambio rige sin
 * reiniciar el worker.
 */
final class ClinicMail
{
    public static function identify(MailMessage $message): MailMessage
    {
        $clinic = ClinicSettings::tryGet();

        // Respaldo: si la guardada dejó de ser válida (p. ej. un dominio ya no permitido), se usa la del .env
        // para que los correos nunca dejen de salir.
        $address = $clinic && self::isAllowedAddress($clinic->mail_from_address)
            ? $clinic->mail_from_address
            : config('mail.from.address');

        if ($clinic && filled($clinic->name)) {
            $message->from($address, $clinic->name);
        } else {
            $message->from($address);
        }

        if ($clinic && filled($clinic->email)) {
            $message->replyTo($clinic->email, $clinic->name);
        }

        return $message;
    }

    /**
     * Datos de marca para las plantillas de correo (encabezado, botón y pie): nombre, logo (URL
     * absoluta), color principal con el color de texto que contrasta, y los datos de contacto cargados.
     *
     * @return array{name: string, logo: ?string, color: string, on_color: string, contact: array<int, string>, email: ?string, website: ?string}
     */
    public static function brand(): array
    {
        $clinic = ClinicSettings::tryGet();

        $color = $clinic?->primary_color;
        $color = is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? $color : '#f59e0b';

        $contact = [];

        if ($clinic) {
            $contact = array_values(array_filter([
                filled($clinic->phone) ? 'Tel. '.$clinic->phone : null,
                filled($clinic->whatsapp) ? 'WhatsApp '.$clinic->whatsapp : null,
                $clinic->address,
                $clinic->business_hours,
                $clinic->facebook,
                $clinic->instagram,
            ], filled(...)));
        }

        return [
            'name' => filled($clinic?->name) ? $clinic->name : (string) config('app.name'),
            'logo' => $clinic && filled($clinic->logo) ? url(Storage::url($clinic->logo)) : null,
            'color' => $color,
            'on_color' => self::contrastingText($color),
            'contact' => $contact,
            'email' => filled($clinic?->email) ? $clinic->email : null,
            'website' => filled($clinic?->website) ? $clinic->website : null,
        ];
    }

    /** Blanco o casi negro, el que mejor se lee sobre el color de fondo (luminancia percibida). */
    private static function contrastingText(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $hex = strlen($hex) === 3 ? preg_replace('/(.)/', '$1$1', $hex) : $hex;
        [$r, $g, $b] = array_map(hexdec(...), str_split($hex, 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#18181b' : '#ffffff';
    }

    /**
     * Dominios desde los que se puede enviar: el de MAIL_FROM_ADDRESS y los de MAIL_ALLOWED_FROM_DOMAINS.
     *
     * @return array<int, string>
     */
    public static function allowedDomains(): array
    {
        $default = Str::after((string) config('mail.from.address'), '@');

        return array_values(array_unique(array_filter([
            Str::lower($default),
            ...array_map(Str::lower(...), (array) config('mail.allowed_from_domains')),
        ])));
    }

    public static function isAllowedAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            && in_array(Str::lower(Str::after($address, '@')), self::allowedDomains(), true);
    }
}
