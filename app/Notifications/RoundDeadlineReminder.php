<?php

namespace App\Notifications;

use App\Models\Quiniela;
use App\Models\Round;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RoundDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Quiniela $quiniela,
        public readonly Round   $round,
        public readonly Carbon  $deadline,
        public readonly int     $hoursLeft,    // 24 or 12
        public readonly int     $missing,      // how many matches still unpredicted
        public readonly int     $total,        // total open matches in round
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $url         = "{$frontendUrl}/quinielas/{$this->quiniela->slug}";

        $deadlineStr = $this->deadline
            ->timezone('America/Mexico_City')
            ->format('d/m/Y \a \l\a\s H:i (T)');

        $subject = $this->hoursLeft === 24
            ? "⏰ 24 horas para pronosticar: {$this->round->name}"
            : "🚨 Última oportunidad: cierra en 12 horas — {$this->round->name}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Tu quiniela **{$this->quiniela->name}** cierra los pronósticos de la **{$this->round->name}** el:")
            ->line("📅 **{$deadlineStr}**")
            ->line("Tienes **{$this->missing} de {$this->total} partido(s)** sin pronosticar en esta jornada.")
            ->action('Ir a pronosticar →', $url)
            ->line('Una vez que inicie el primer partido de la jornada, ya no podrás modificar tus pronósticos.')
            ->salutation('¡Buena suerte! — El equipo de MatchPick');
    }
}
