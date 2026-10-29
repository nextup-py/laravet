<?php

namespace App\Notifications;

use App\Models\ProblemReport;
use App\Support\ClinicMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Aviso al correo de soporte de un reporte nuevo de «Ayuda → Reportar un problema». Se responde
 * directamente a quien reportó (Reply-To) y lleva la captura adjunta si la hay.
 */
class ProblemReportNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ProblemReport $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->report;

        $message = ClinicMail::identify((new MailMessage)
            ->subject('['.$report->type->getLabel().'] '.$report->user_name)
            ->greeting('Nuevo reporte')
            ->line('**Tipo:** '.$report->type->getLabel())
            ->line('**De:** '.$report->user_name.($report->user?->email ? ' ('.$report->user->email.')' : ''))
            ->line('**Pantalla:** '.($report->screen ?: 'sin indicar'))
            ->line('**Navegador:** '.($report->user_agent ?: 'sin indicar'))
            ->line('**Versión del sistema:** '.($report->app_version ?: 'sin indicar'))
            ->line($report->description), withReplyTo: false);

        if ($report->user?->email) {
            $message->replyTo($report->user->email, $report->user_name);
        }

        if ($report->attachment && Storage::disk('problem-reports')->exists($report->attachment)) {
            $message->attach(Storage::disk('problem-reports')->path($report->attachment));
        }

        return $message;
    }
}
