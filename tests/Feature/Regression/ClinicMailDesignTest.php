<?php

use App\Enums\StaffAlert;
use App\Models\User;
use App\Models\Vaccination;
use App\Notifications\StaffAlertNotification;
use App\Notifications\VaccinationNotification;
use App\Settings\ClinicSettings;
use App\Support\ClinicMail;
use App\Support\ReminderMessage;
use App\Support\WhatsappLink;

function clinicBranding(array $values = []): void
{
    $settings = app(ClinicSettings::class);

    foreach ([
        'name' => 'Mbopivet',
        'primary_color' => '#7c3aed',
        'phone' => '0981 211 112',
        'whatsapp' => '0981 211 112',
        'address' => 'Independencia Nacional c/ Bicentenario',
        'business_hours' => 'Lunes a Sábado de 8:00 a 17:00 hs',
        'email' => 'mbopivet@gmail.com',
        ...$values,
    ] as $property => $value) {
        $settings->{$property} = $value;
    }

    $settings->save();
}

it('arma el enlace de WhatsApp con el prefijo de Paraguay y el texto codificado', function () {
    expect(WhatsappLink::url('0981 211 112'))->toBe('https://wa.me/595981211112')
        ->and(WhatsappLink::url('+595 981 211112', 'Hola, ¿turno?'))->toBe('https://wa.me/595981211112?text='.rawurlencode('Hola, ¿turno?'));
});

it('el asunto del recordatorio dice la mascota, la vacuna y si vence o venció', function () {
    $upcoming = Vaccination::factory()->proximaAVencer(2)->create(['vaccine' => 'Séptuple']);
    $overdue = Vaccination::factory()->create(['vaccine' => 'Rabia', 'next_application' => now()->subDays(5)]);

    expect(ReminderMessage::subject($upcoming))->toBe(
        $upcoming->pet->name.': la vacuna Séptuple vence el '.$upcoming->next_application->format('d/m/Y'),
    )->and(ReminderMessage::subject($overdue))->toContain('la vacuna Rabia venció el');
});

it('el recordatorio al propietario trae el botón de WhatsApp de la clínica y no el enlace al panel', function () {
    clinicBranding();
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create(['vaccine' => 'Séptuple']);

    $mail = (new VaccinationNotification($vaccination))->toMail($vaccination->pet->owner);

    expect($mail->actionText)->toBe('Agendar turno por WhatsApp')
        ->and($mail->actionUrl)->toStartWith('https://wa.me/595981211112?text=')
        ->and($mail->actionUrl)->not->toContain('/pets/')
        ->and($mail->subject)->toBe(ReminderMessage::subject($vaccination))
        ->and($mail->salutation)->toBe('Saludos, Mbopivet');
});

it('sin WhatsApp cargado el recordatorio no lleva botón', function () {
    clinicBranding(['whatsapp' => null]);
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create();

    $mail = (new VaccinationNotification($vaccination))->toMail($vaccination->pet->owner);

    expect($mail->actionText)->toBeNull();
});

it('la plantilla muestra el nombre, los datos de contacto y el color de la clínica, no «Laravet»', function () {
    config(['app.name' => 'Laravet']);
    clinicBranding();
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create();

    $html = (string) (new VaccinationNotification($vaccination))->toMail($vaccination->pet->owner)->render();

    expect($html)
        ->toContain('Mbopivet')
        ->toContain('Independencia Nacional c/ Bicentenario')
        ->toContain('Lunes a Sábado de 8:00 a 17:00 hs')
        ->toContain('Puedes responder este correo: tu respuesta llega a mbopivet@gmail.com.')
        ->toContain('#7c3aed')
        ->toContain('Todos los derechos reservados.')
        ->not->toContain('Laravet');
});

it('los avisos al personal usan la misma plantilla y firman con la clínica', function () {
    config(['app.name' => 'Laravet']);
    clinicBranding();
    $user = User::factory()->create();

    $html = (string) (new StaffAlertNotification(StaffAlert::Vaccinations, 3))->toMail($user)->render();

    expect($html)
        ->toContain('Saludos,')
        ->toContain('Mbopivet')
        ->toContain('Ver en el panel')
        ->not->toContain('Laravet');
});

it('el texto de ayuda del botón está en tuteo', function () {
    clinicBranding();
    $user = User::factory()->create();

    $html = (string) (new StaffAlertNotification(StaffAlert::Vaccinations, 3))->toMail($user)->render();

    expect($html)->toContain('Si tienes problemas al hacer clic')->not->toContain('copie');
});

it('el botón usa un texto oscuro si el color de la clínica es claro', function () {
    clinicBranding(['primary_color' => '#fde68a']);

    expect(ClinicMail::brand()['on_color'])->toBe('#18181b');

    clinicBranding(['primary_color' => '#1e3a8a']);

    expect(ClinicMail::brand()['on_color'])->toBe('#ffffff');
});

it('un color inválido cae al color por defecto', function () {
    clinicBranding(['primary_color' => 'rojo; background:url(x)']);

    expect(ClinicMail::brand()['color'])->toBe('#f59e0b');
});

it('la migración cambia el texto original del recordatorio y respeta el que la clínica editó', function () {
    $migration = require database_path('settings/2026_10_29_160000_update_default_reminder_email_message.php');
    $settings = app(ClinicSettings::class);

    $settings->reminder_email_message = ClinicSettings::PREVIOUS_REMINDER_EMAIL;
    $settings->save();
    $migration->up();
    app()->forgetInstance(ClinicSettings::class);
    expect(app(ClinicSettings::class)->reminder_email_message)->toBe(ClinicSettings::DEFAULT_REMINDER_EMAIL);

    $settings = app(ClinicSettings::class);
    $settings->reminder_email_message = 'Texto propio de la clínica.';
    $settings->save();
    $migration->up();
    app()->forgetInstance(ClinicSettings::class);
    expect(app(ClinicSettings::class)->reminder_email_message)->toBe('Texto propio de la clínica.');
});
