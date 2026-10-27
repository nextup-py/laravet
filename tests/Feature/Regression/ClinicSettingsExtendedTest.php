<?php

use App\Filament\Pages\ClinicSettingsPage;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\VaccinationResource;
use App\Models\Vaccination;
use App\Notifications\VaccinationNotification;
use App\Settings\ClinicSettings;
use App\Support\ReminderMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function clinicSettings(): ClinicSettings
{
    return app(ClinicSettings::class);
}

it('solo el admin abre la configuración de la clínica', function () {
    actingAsRole('veterinarian');
    expect(ClinicSettingsPage::canAccess())->toBeFalse();

    actingAsRole('admin');
    expect(ClinicSettingsPage::canAccess())->toBeTrue();
});

it('el admin guarda recordatorios, textos, PDF, zona horaria y listas', function () {
    actingAsRole('admin');

    Livewire::test(ClinicSettingsPage::class)
        ->fillForm([
            'short_name' => 'Mbopi',
            'timezone' => 'America/Asuncion',
            'reminders_enabled' => false,
            'reminder_days_before' => 5,
            'reminder_days_overdue' => 10,
            'reminder_time' => '08:30',
            'reminder_email_message' => 'Hola {propietario}',
            'pdf_footer' => 'Gracias por confiar en nosotros',
            'vaccines' => ['Rabia', 'Séptuple'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = clinicSettings()->refresh();

    expect($settings->short_name)->toBe('Mbopi')
        ->and($settings->reminders_enabled)->toBeFalse()
        ->and($settings->reminder_days_before)->toBe(5)
        ->and($settings->reminder_time)->toBe('08:30')
        ->and($settings->pdf_footer)->toBe('Gracias por confiar en nosotros')
        ->and($settings->vaccines)->toBe(['Rabia', 'Séptuple']);
});

it('rechaza un SVG como logo, favicon o firma', function () {
    Storage::fake();
    actingAsRole('admin');

    $svg = fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');

    Livewire::test(ClinicSettingsPage::class)
        ->fillForm(['logo' => $svg(), 'favicon' => $svg(), 'pdf_signature' => $svg()])
        ->call('save')
        ->assertHasFormErrors(['logo', 'favicon', 'pdf_signature']);
});

it('las listas de los formularios salen de la configuración y caen a la lista inicial si quedan vacías', function () {
    $settings = clinicSettings();
    $settings->vaccines = ['Vacuna Propia'];
    $settings->manufacturers = ['Fabricante Propio'];
    $settings->surgery_types = ['Zeta', 'Alfa'];
    $settings->test_types = ['Prueba Propia'];
    $settings->save();

    expect(VaccinationResource::vaccineOptions())->toBe(['Vacuna Propia' => 'Vacuna Propia'])
        ->and(VaccinationResource::manufacturerOptions())->toHaveKey('Fabricante Propio')
        ->and(array_keys(SurgeryResource::typeOptions()))->toBe(['Alfa', 'Zeta'])
        ->and(TestResource::typeOptions())->toHaveKey('Prueba Propia');

    $settings->vaccines = [];
    $settings->save();

    expect(VaccinationResource::vaccineOptions())->toHaveKey('Séptuple');
});

it('los textos de recordatorio usan la plantilla configurada o el texto por defecto', function () {
    $vaccination = Vaccination::factory()->proximaAVencer(2)->create();
    $owner = $vaccination->pet->owner;

    expect(ReminderMessage::email($vaccination))
        ->toContain($vaccination->vaccine, $vaccination->pet->name, 'está por vencer');

    $settings = clinicSettings();
    $settings->reminder_whatsapp_message = 'Hola {propietario}, {mascota} necesita {vacuna} ({estado} el {fecha}) - {clinica}';
    $settings->save();

    expect(ReminderMessage::whatsapp($vaccination))
        ->toBe("Hola {$owner->first_name}, {$vaccination->pet->name} necesita {$vaccination->vaccine} (está por vencer el "
            .$vaccination->next_application->format('d/m/Y').') - '.$settings->name);
});

it('el comando respeta el interruptor y la ventana de días configurados', function () {
    Notification::fake();
    $vencidaHaceDiezDias = Vaccination::factory()->create(['next_application' => now()->subDays(10)]);

    $settings = clinicSettings();
    $settings->reminders_enabled = false;
    $settings->save();

    test()->artisan('send:vaccination-notifications')->assertSuccessful();
    Notification::assertNothingSent();

    $settings->reminders_enabled = true;
    $settings->reminder_days_overdue = 5;
    $settings->save();

    test()->artisan('send:vaccination-notifications')->assertSuccessful();
    Notification::assertNothingSent();

    $settings->reminder_days_overdue = 15;
    $settings->save();

    test()->artisan('send:vaccination-notifications')->assertSuccessful();
    Notification::assertSentTo($vencidaHaceDiezDias->pet->owner, VaccinationNotification::class);
});

it('los PDFs incluyen el pie, la firma y el texto legal configurados', function () {
    $settings = clinicSettings();
    $settings->pdf_footer = 'Pie personalizado';
    $settings->pdf_legal_text = 'Documento válido con sello';
    $settings->save();

    $html = view('pdf.layout', ['clinic' => $settings])->render();

    expect($html)->toContain('Pie personalizado', 'Documento válido con sello');
});
