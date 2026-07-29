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
        // Vacunaciones cuya próxima aplicación es en los próximos 3 días y que aún
        // no recibieron un recordatorio (evita reenviar en cada corrida del schedule).
        $vaccinations = Vaccination::query()
            ->whereNull('reminder_sent_at')
            ->where('next_application', '<=', now()->addDays(3))
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
