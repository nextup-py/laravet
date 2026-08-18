<?php

namespace App\Notifications;

use App\Filament\Resources\PetResource;
use App\Models\Vaccination;
use App\Settings\ClinicSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class VaccinationNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected Vaccination $vaccination;

    /**
     * Create a new notification instance.
     */
    public function __construct(Vaccination $vaccination)
    {
        $this->vaccination = $vaccination;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $clinic = app(ClinicSettings::class);

        return (new MailMessage)
            ->greeting('¡Hola, '.$this->vaccination->pet->owner->first_name.'!')
            ->subject(__('Recordatorio de vacunación para tu mascota.'))
            ->line('La vacuna de tu mascota '.$this->vaccination->pet->name.' está por vencer el '.$this->vaccination->next_application)
            ->action('Ver detalles', PetResource::getUrl('edit', ['record' => $this->vaccination->pet_id]))
            ->salutation('Saludos, '.$clinic->name);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
