<?php

namespace App\Notifications;

use App\Models\Vaccination;
use App\Settings\ClinicSettings;
use App\Support\ClinicMail;
use App\Support\ReminderMessage;
use App\Support\WhatsappLink;
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
        $clinic = ClinicSettings::tryGet();
        $pet = $this->vaccination->pet;

        $message = (new MailMessage)
            ->greeting('¡Hola, '.$pet->owner->first_name.'!')
            ->subject(ReminderMessage::subject($this->vaccination))
            ->line(ReminderMessage::email($this->vaccination))
            ->salutation('Saludos, '.($clinic?->name ?? config('app.name')));

        // El propietario no tiene acceso al panel: el botón lo lleva a escribirle a la clínica.
        if ($clinic && filled($clinic->whatsapp)) {
            $message->action('Agendar turno por WhatsApp', WhatsappLink::url(
                $clinic->whatsapp,
                'Hola, quiero agendar un turno de vacunación para '.$pet->name.' ('.$this->vaccination->vaccine.').',
            ));
        }

        return ClinicMail::identify($message);
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
