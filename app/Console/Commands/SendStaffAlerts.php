<?php

namespace App\Console\Commands;

use App\Enums\StaffAlert;
use App\Models\User;
use App\Notifications\StaffAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendStaffAlerts extends Command
{
    protected $signature = 'send:staff-alerts';

    protected $description = 'Envía al personal los avisos diarios (vacunas, cirugías y pruebas) según las preferencias de cada usuario.';

    public function handle(): void
    {
        User::query()->whereHas('roles')->each(function (User $user): void {
            foreach (StaffAlert::cases() as $alert) {
                if ($user->notificationChannels($alert) === []) {
                    continue;
                }

                $count = $alert->query($user)->count();

                // Un aviso por usuario, tipo y día, aunque el comando se vuelva a correr.
                if ($count === 0 || ! Cache::add("staff-alert:{$user->id}:{$alert->value}:".today()->toDateString(), true, now()->addDay())) {
                    continue;
                }

                $user->notify(new StaffAlertNotification($alert, $count));
            }
        });
    }
}
