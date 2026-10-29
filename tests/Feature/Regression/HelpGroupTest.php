<?php

use App\Filament\Pages\ClinicSettingsPage;
use App\Filament\Pages\ReleaseNotesPage;
use App\Filament\Pages\SupportPage;
use App\Settings\ClinicSettings;
use App\Support\ReleaseNotes;
use Livewire\Livewire;

it('cualquier usuario del panel abre Soporte y ve el correo de soporte configurado', function () {
    $settings = app(ClinicSettings::class);
    $settings->support_email = 'ayuda@example.com';
    $settings->save();

    actingAsRole('assistant');

    $this->get(SupportPage::getUrl())
        ->assertOk()
        ->assertSee('ayuda@example.com')
        ->assertSee('mailto:ayuda@example.com?subject=', false);
});

it('el correo de soporte arranca con el valor inicial y es obligatorio en Datos de la clínica', function () {
    expect(app(ClinicSettings::class)->support_email)->toBe(ClinicSettings::DEFAULT_SUPPORT_EMAIL);

    actingAsRole('admin');

    Livewire::test(ClinicSettingsPage::class)
        ->fillForm(['support_email' => ''])
        ->call('save')
        ->assertHasFormErrors(['support_email' => 'required'])
        ->fillForm(['support_email' => 'no-es-un-correo'])
        ->call('save')
        ->assertHasFormErrors(['support_email' => 'email']);
});

it('las novedades salen de los archivos de resources/novedades, de la más nueva a la más vieja', function () {
    $entries = ReleaseNotes::all();

    expect($entries)->not->toBeEmpty()
        ->and($entries->pluck('date')->map->format('Y-m-d')->all())
        ->toBe($entries->pluck('date')->map->format('Y-m-d')->sortDesc()->values()->all());

    $entries->each(function (array $entry): void {
        expect($entry['title'])->not->toBeEmpty()->and($entry['body'])->not->toBeEmpty();
    });
});

it('cualquier usuario del panel abre Novedades', function () {
    actingAsRole('veterinarian');

    $this->get(ReleaseNotesPage::getUrl())
        ->assertOk()
        ->assertSee('Novedades del sistema')
        ->assertSee(ReleaseNotes::all()->first()['title']);
});

it('las novedades escapan el HTML de los archivos', function () {
    $html = (new ReleaseNotesPage)->getEntries();

    expect($html->pluck('html')->implode(''))->not->toContain('<script');
});

it('Ayuda agrupa Guía de uso, Soporte y Novedades', function () {
    expect(SupportPage::getNavigationGroup())->toBe('Ayuda')
        ->and(ReleaseNotesPage::getNavigationGroup())->toBe('Ayuda');
});
