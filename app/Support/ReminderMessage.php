<?php

namespace App\Support;

use App\Enums\VaccinationStatus;
use App\Models\Vaccination;
use App\Settings\ClinicSettings;
use Illuminate\Support\Carbon;

/**
 * Arma el texto de un recordatorio de vacunación (email o WhatsApp) a partir
 * de la plantilla que la clínica configuró, con marcadores entre llaves.
 */
final class ReminderMessage
{
    /** Marcador => descripción, para la ayuda del formulario de configuración. */
    public const PLACEHOLDERS = [
        '{propietario}' => 'nombre de pila del propietario',
        '{mascota}' => 'nombre de la mascota',
        '{vacuna}' => 'nombre de la vacuna',
        '{fecha}' => 'fecha de la próxima aplicación (d/m/Y)',
        '{estado}' => '«venció» o «está por vencer»',
        '{clinica}' => 'nombre de la clínica',
    ];

    public static function email(Vaccination $vaccination): string
    {
        $clinic = ClinicSettings::tryGet();

        return self::render($clinic?->reminder_email_message ?: ClinicSettings::DEFAULT_REMINDER_EMAIL, $vaccination);
    }

    public static function whatsapp(Vaccination $vaccination): string
    {
        $clinic = ClinicSettings::tryGet();

        return self::render($clinic?->reminder_whatsapp_message ?: ClinicSettings::DEFAULT_REMINDER_WHATSAPP, $vaccination);
    }

    public static function render(string $template, Vaccination $vaccination): string
    {
        $next = Carbon::parse($vaccination->next_application);
        $overdue = $vaccination->status === VaccinationStatus::Overdue || $next->startOfDay()->lt(today());

        return strtr($template, [
            '{propietario}' => (string) $vaccination->pet->owner?->first_name,
            '{mascota}' => $vaccination->pet->name,
            '{vacuna}' => $vaccination->vaccine,
            '{fecha}' => $next->format('d/m/Y'),
            '{estado}' => $overdue ? 'venció' : 'está por vencer',
            '{clinica}' => ClinicSettings::tryGet()?->name ?? config('app.name'),
        ]);
    }
}
