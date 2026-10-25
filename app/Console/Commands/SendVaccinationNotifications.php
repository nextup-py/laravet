<?php

namespace App\Console\Commands;

use App\Models\Vaccination;
use App\Notifications\VaccinationNotification;
use Illuminate\Console\Command;

class SendVaccinationNotifications extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'send:vaccination-notifications';

    /** Días antes del vencimiento desde los que se avisa. */
    private const DAYS_BEFORE = 3;

    /** Antigüedad máxima de una vacunación vencida para que todavía se avise. */
    private const MAX_DAYS_OVERDUE = 30;

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
        // Solo vacunaciones vigentes (la última aplicación de cada vacuna por mascota) cuya
        // próxima aplicación es en los próximos 3 días, o venció hace como máximo 30 días,
        // y que aún no recibieron un recordatorio (evita reenviar en cada corrida del schedule).
        $vaccinations = Vaccination::query()
            ->current()
            ->whereNull('reminder_sent_at')
            ->whereBetween('next_application', [
                today()->subDays(self::MAX_DAYS_OVERDUE)->toDateString(),
                today()->addDays(self::DAYS_BEFORE)->endOfDay()->toDateTimeString(),
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
