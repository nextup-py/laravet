<?php

namespace App\Console\Commands;

use App\Models\Vaccination;
use App\Notifications\VaccinationNotification;
use App\Settings\ClinicSettings;
use Illuminate\Console\Command;

class SendVaccinationNotifications extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'send:vaccination-notifications';

    /**
     * Descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios de vacunación a los dueños de las mascotas.';

    /**
     * Ejecutar el comando de consola.
     */
    public function handle(): void
    {
        $clinic = ClinicSettings::tryGet();

        if ($clinic && ! $clinic->reminders_enabled) {
            return;
        }

        // Días antes del vencimiento desde los que se avisa, y antigüedad máxima de una
        // vacunación vencida para que todavía se avise (configurables en «Datos de la clínica»).
        $daysBefore = $clinic?->reminder_days_before ?? 3;
        $maxDaysOverdue = $clinic?->reminder_days_overdue ?? 30;

        // Solo vacunaciones vigentes (la última aplicación de cada vacuna por mascota) cuya
        // próxima aplicación es en los próximos 3 días, o venció hace como máximo 30 días,
        // y que aún no recibieron un recordatorio (evita reenviar en cada corrida del schedule).
        $vaccinations = Vaccination::query()
            ->current()
            ->whereNull('reminder_sent_at')
            ->whereBetween('next_application', [
                today()->subDays($maxDaysOverdue)->toDateString(),
                today()->addDays($daysBefore)->endOfDay()->toDateTimeString(),
            ])
            ->with(['pet.owner'])
            ->get();

        foreach ($vaccinations as $vaccination) {
            $owner = $vaccination->pet?->owner;

            if (! $owner) {
                continue;
            }

            $owner->notify(new VaccinationNotification($vaccination));

            $vaccination->reminder_sent_at = now();
            $vaccination->save();
        }
    }
}
