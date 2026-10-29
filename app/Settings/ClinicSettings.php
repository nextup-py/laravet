<?php

namespace App\Settings;

use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Settings;

class ClinicSettings extends Settings
{
    /** Textos por defecto de los recordatorios; admiten los marcadores de `ReminderMessage::PLACEHOLDERS`. */
    public const DEFAULT_REMINDER_EMAIL = 'La vacuna {vacuna} de {mascota} {estado} el {fecha}. Mantenerla al día protege a {mascota} de enfermedades graves. Escríbenos por WhatsApp o responde este correo y te agendamos un turno.';

    /** Texto anterior del recordatorio por correo; la migración lo reemplaza solo si la clínica no lo editó. */
    public const PREVIOUS_REMINDER_EMAIL = 'La vacuna {vacuna} de tu mascota {mascota} {estado} el {fecha}.';

    public const DEFAULT_REMINDER_WHATSAPP = 'Hola {propietario}, te escribimos de {clinica}. La vacuna {vacuna} de {mascota} {estado} el {fecha}. ¿Te agendamos un turno?';

    /** Correo de soporte del sistema por defecto; se edita en «Datos de la clínica». */
    public const DEFAULT_SUPPORT_EMAIL = 'laravet@nextup.com.py';

    /** Listas iniciales; la clínica las edita desde «Datos de la clínica → Listas». */
    public const DEFAULT_VACCINES = [
        'Séptuple', 'Rabia', 'Moquillo', 'Parvovirus', 'Adenovirus', 'Leptospirosis', 'Parainfluenza',
        'Bordetella', 'Leucemia Felina', 'Panleucopenia', 'Calicivirus', 'Rinotraqueítis Felina',
        'Triple Felina', 'Lyme', 'Gripe Canina', 'Tos de las Perreras', 'Coronavirus Canino', 'Giardia',
        'Rabia Recombinante', 'Antirrábica', 'Herpesvirus Equino', 'Mixomatosis',
        'Enfermedad Hemorrágica Vírica', 'Vacuna Polivalente',
    ];

    public const DEFAULT_MANUFACTURERS = [
        'Zoetis', 'MSD', 'Elanco', 'Boehringer Ingelheim', 'Merial', 'Virbac', 'Ceva', 'Heska',
        'Bayer', 'Vetoquinol', 'Phibro', 'Hipra', 'Biogénesis Bagó', 'Bioiberica', 'Syva',
        'IDT Biologika', 'VECOL', 'Karnov', 'Labiana',
    ];

    public const DEFAULT_SURGERY_TYPES = [
        'Esterilización', 'Ovariohisterectomía', 'Castración', 'Cirugía Dental', 'Extracción de Tumores',
        'Cirugía de Cuerpo Extraño', 'Reparación de Fracturas', 'Cesárea', 'Amputación', 'Enucleación Ocular',
        'Cirugía de Ligamento Cruzado', 'Gastropexia Preventiva', 'Desungulación', 'Herniorrafia',
        'Laparotomía Exploratoria', 'Onicectomía', 'Cistotomía', 'Uretrostomía Perineal', 'Esplenectomía',
        'Tiroidectomía', 'Cirugía de Luxación de Rótula', 'Toracotomía', 'Resección Intestinal',
        'Cirugía de Glándulas Perianales', 'Sutura de herida', 'Extracción dental', 'Desobstrucción uretral',
    ];

    public const DEFAULT_TEST_TYPES = [
        'Hematología', 'Bioquímica', 'Inmunología', 'Microbiología', 'Parasitología', 'Uroanálisis',
        'Coprología', 'Cultivo', 'Prueba Rápida', 'PCR', 'Serología', 'Otros',
    ];

    public string $name;

    public ?string $short_name;

    public string $timezone;

    public ?string $logo;

    public ?string $favicon;

    public ?string $primary_color;

    public ?string $address;

    public ?string $phone;

    public ?string $whatsapp;

    public ?string $email;

    /** Dirección de envío de los correos, obligatoria y de un dominio verificado (ver ClinicMail, que usa MAIL_FROM_ADDRESS de respaldo). */
    public string $mail_from_address;

    /** Correo de soporte del sistema: se muestra en Ayuda → Soporte y recibe los reportes de problemas. */
    public string $support_email;

    public ?string $business_hours;

    public ?string $ruc;

    public ?string $razon_social;

    public ?string $facebook;

    public ?string $instagram;

    public ?string $website;

    public bool $reminders_enabled;

    public int $reminder_days_before;

    public int $reminder_days_overdue;

    public string $reminder_time;

    public ?string $reminder_email_message;

    public ?string $reminder_whatsapp_message;

    public ?string $pdf_footer;

    public ?string $pdf_legal_text;

    public ?string $pdf_signature;

    /** @var array<int, string> */
    public array $vaccines;

    /** @var array<int, string> */
    public array $manufacturers;

    /** @var array<int, string> */
    public array $surgery_types;

    /** @var array<int, string> */
    public array $test_types;

    public static function group(): string
    {
        return 'clinic';
    }

    /**
     * Los settings, o null si todavía no se pueden leer (instalación nueva sin
     * migrar, CI sin base, migración de settings pendiente). Quien lo use
     * cae a sus valores por defecto.
     */
    public static function tryGet(): ?self
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            // Spatie carga los valores de forma perezosa: se fuerza acá para que un
            // fallo (propiedades sin migrar) se capture y no explote al leer la primera.
            $settings = app(self::class);
            $settings->name;

            return $settings;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Opciones (clave = etiqueta) de una de las listas editables: vaccines,
     * manufacturers, surgery_types o test_types. Si los settings no se pueden
     * leer o la lista quedó vacía, usa la lista inicial.
     *
     * @return array<string, string>
     */
    public static function catalog(string $property): array
    {
        $defaults = [
            'vaccines' => self::DEFAULT_VACCINES,
            'manufacturers' => self::DEFAULT_MANUFACTURERS,
            'surgery_types' => self::DEFAULT_SURGERY_TYPES,
            'test_types' => self::DEFAULT_TEST_TYPES,
        ];

        $values = self::tryGet()?->{$property} ?: $defaults[$property];

        return array_combine($values, $values);
    }
}
