<?php

namespace App\Support;

use App\Settings\ClinicSettings;
use Illuminate\Notifications\Messages\MailMessage;
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
