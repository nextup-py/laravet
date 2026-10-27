<?php

namespace App\Notifications;

use App\Enums\StaffAlert;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso diario al personal (ver StaffAlert). Los canales salen de las
 * preferencias del usuario: «database» es la campanita del panel.
 */
class StaffAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public StaffAlert $alert, public int $count) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannels($this->alert);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            ...FilamentNotification::make()
                ->title($this->alert->title($this->count))
                ->icon($this->alert->icon())
                ->warning()
                ->actions([
                    Action::make('view')->label('Ver')->url($this->alert->url())->markAsRead(),
                ])
                ->getDatabaseMessage(),
            'alert' => $this->alert->value,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->alert->title($this->count))
            ->greeting('Hola, '.$notifiable->name)
            ->line($this->alert->title($this->count).'.')
            ->action('Ver en el panel', $this->alert->url())
            ->line('Puedes elegir qué avisos recibir y por qué canal desde tu perfil.');
    }
}
