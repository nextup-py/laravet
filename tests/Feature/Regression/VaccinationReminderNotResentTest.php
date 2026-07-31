<?php

use App\Models\Vaccination;
use App\Notifications\VaccinationNotification;
use Illuminate\Support\Facades\Notification;

it('no reenvía el recordatorio a una vacunación que ya lo tiene enviado', function () {
    Notification::fake();

    $vencidaConRecordatorio = Vaccination::factory()->recordatorioYaEnviado()->create([
        'next_application' => now()->addDay(),
    ]);
    $vencidaSinRecordatorio = Vaccination::factory()->proximaAVencer(2)->create();

    test()->artisan('send:vaccination-notifications')->assertSuccessful();

    Notification::assertSentTo($vencidaSinRecordatorio->pet->owner, VaccinationNotification::class);
    Notification::assertNotSentTo($vencidaConRecordatorio->pet->owner, VaccinationNotification::class);

    expect($vencidaSinRecordatorio->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($vencidaConRecordatorio->refresh()->reminder_sent_at)->not->toBeNull();
});
