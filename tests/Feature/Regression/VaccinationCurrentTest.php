<?php

use App\Enums\VaccinationStatus;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Filament\Widgets\UpcomingVaccinationsWidget;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Notifications\VaccinationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Una mascota con una aplicación vieja (vencida) y otra más reciente de la misma vacuna. */
function mascotaConVacunaRenovada(string $vacuna = 'Rabia'): array
{
    $pet = Pet::factory()->create();
    $vieja = Vaccination::factory()->create([
        'pet_id' => $pet->id,
        'vaccine' => $vacuna,
        'application_date' => today()->subYears(2),
        'next_application' => today()->subYear(),
    ]);
    $nueva = Vaccination::factory()->create([
        'pet_id' => $pet->id,
        'vaccine' => $vacuna,
        'application_date' => today()->subMonths(2),
        'next_application' => today()->addMonths(10),
    ]);

    return [$pet, $vieja, $nueva];
}

it('una aplicación con otra más reciente de la misma vacuna y mascota queda Renovada', function () {
    [, $vieja, $nueva] = mascotaConVacunaRenovada();

    expect($vieja->fresh()->status)->toBe(VaccinationStatus::Renewed)
        ->and($nueva->fresh()->status)->toBe(VaccinationStatus::UpToDate)
        ->and(Vaccination::renewed()->pluck('id')->all())->toBe([$vieja->id])
        ->and(Vaccination::current()->pluck('id')->all())->toBe([$nueva->id]);
});

it('otra vacuna u otra mascota no renueva la aplicación', function () {
    $pet = Pet::factory()->create();
    $rabia = Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'application_date' => today()->subYears(2), 'next_application' => today()->subYear()]);
    Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Séptuple', 'application_date' => today()->subDay(), 'next_application' => today()->addYear()]);
    Vaccination::factory()->create(['vaccine' => 'Rabia', 'application_date' => today()->subDay(), 'next_application' => today()->addYear()]);

    expect($rabia->fresh()->status)->toBe(VaccinationStatus::Overdue);
});

it('con la misma fecha de aplicación la de mayor id es la vigente', function () {
    $pet = Pet::factory()->create();
    $a = Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'application_date' => today()->subDay(), 'next_application' => today()->addYear()]);
    $b = Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'application_date' => today()->subDay(), 'next_application' => today()->addYear()]);

    expect($a->fresh()->status)->toBe(VaccinationStatus::Renewed)
        ->and($b->fresh()->status)->toBe(VaccinationStatus::UpToDate);
});

it('el filtro de estado separa vigentes de renovadas y Renovada es una opción', function () {
    actingAsRole('admin');
    [, $vieja, $nueva] = mascotaConVacunaRenovada();
    $vencidaVigente = Vaccination::factory()->create(['next_application' => today()->subDays(5)]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('status', 'renewed')
        ->assertCanSeeTableRecords([$vieja])
        ->assertCanNotSeeTableRecords([$nueva, $vencidaVigente]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('status', 'overdue')
        ->assertCanSeeTableRecords([$vencidaVigente])
        ->assertCanNotSeeTableRecords([$vieja]);
});

it('la tabla muestra la marca Renovada', function () {
    actingAsRole('admin');
    mascotaConVacunaRenovada();

    Livewire::test(ListVaccinations::class)->assertSee('Renovada');
});

it('el contador del menú y el widget de inicio ignoran las renovadas', function () {
    actingAsRole('admin');
    [, $vieja, $nueva] = mascotaConVacunaRenovada();
    $vencidaVigente = Vaccination::factory()->create(['next_application' => today()->subDays(5)]);

    expect(VaccinationResource::getNavigationBadge())->toBe('1');

    Livewire::test(UpcomingVaccinationsWidget::class)
        ->assertCanSeeTableRecords([$vencidaVigente])
        ->assertCanNotSeeTableRecords([$vieja, $nueva]);
});

it('el filtro de vacunas de la lista de mascotas ignora las renovadas', function () {
    actingAsRole('admin');
    [$petRenovada] = mascotaConVacunaRenovada();
    $petVencida = Pet::factory()->create();
    Vaccination::factory()->create(['pet_id' => $petVencida->id, 'next_application' => today()->subDays(3)]);

    Livewire::test(ListPets::class)
        ->filterTable('vaccination_due', true)
        ->assertCanSeeTableRecords([$petVencida])
        ->assertCanNotSeeTableRecords([$petRenovada]);
});

it('los recordatorios solo se envían por vigentes: próximas o vencidas hace ≤ 30 días', function () {
    Notification::fake();

    [, $viejaRenovada] = mascotaConVacunaRenovada();
    $proxima = Vaccination::factory()->create(['next_application' => today()->addDays(2)]);
    $vencidaReciente = Vaccination::factory()->create(['next_application' => today()->subDays(10)]);
    $vencidaAntigua = Vaccination::factory()->create(['next_application' => today()->subDays(90)]);
    $lejana = Vaccination::factory()->create(['next_application' => today()->addDays(30)]);

    test()->artisan('send:vaccination-notifications')->assertSuccessful();

    Notification::assertSentTo($proxima->pet->owner, VaccinationNotification::class);
    Notification::assertSentTo($vencidaReciente->pet->owner, VaccinationNotification::class);
    Notification::assertNotSentTo($vencidaAntigua->pet->owner, VaccinationNotification::class);
    Notification::assertNotSentTo($lejana->pet->owner, VaccinationNotification::class);
    Notification::assertNotSentTo($viejaRenovada->pet->owner, VaccinationNotification::class);

    expect($viejaRenovada->fresh()->reminder_sent_at)->toBeNull()
        ->and($vencidaAntigua->fresh()->reminder_sent_at)->toBeNull();
});

it('el correo dice "venció" para una vacuna ya vencida y "está por vencer" para una próxima', function () {
    $vencida = Vaccination::factory()->create(['next_application' => today()->subDays(4)]);
    $proxima = Vaccination::factory()->create(['next_application' => today()->addDays(2)]);

    $mailVencida = (new VaccinationNotification($vencida->load('pet.owner')))->toMail($vencida->pet->owner);
    $mailProxima = (new VaccinationNotification($proxima->load('pet.owner')))->toMail($proxima->pet->owner);

    expect(implode(' ', $mailVencida->introLines))->toContain('venció el')
        ->and(implode(' ', $mailProxima->introLines))->toContain('está por vencer el');
});

it('con tres o más aplicaciones de la misma vacuna, solo la última queda vigente y las demás Renovadas', function () {
    $pet = Pet::factory()->create();
    $ids = collect([30, 20, 10])->map(fn (int $dias) => Vaccination::factory()->create([
        'pet_id' => $pet->id,
        'vaccine' => 'Rabia',
        'application_date' => today()->subDays($dias),
        'next_application' => today()->addYear()->subDays($dias),
    ])->id);

    $porId = Vaccination::query()->withRenewedFlag()->get()->keyBy('id');

    expect($porId[$ids[0]]->status)->toBe(VaccinationStatus::Renewed)
        ->and($porId[$ids[1]]->status)->toBe(VaccinationStatus::Renewed)
        ->and($porId[$ids[2]]->status)->toBe(VaccinationStatus::UpToDate);
});

it('la marca is_renewed es una subconsulta escalar con limit 1 (MySQL falla con 1242 si devuelve varias filas)', function () {
    // SQLite tolera subconsultas escalares con varias filas y MySQL no: se verifica el SQL generado.
    expect(Vaccination::query()->withRenewedFlag()->toSql())
        ->toMatch('/limit 1\) as [`"]is_renewed[`"]/');
});
