<?php

namespace App\Notifications;

use App\Models\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the empresa's owner that prospecting stopped on one of their numbers, and why. */
class ProspectingPaused extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly WhatsAppChannel $channel,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $canal = $this->channel->nome;

        return (new MailMessage)
            ->subject(__('messages.channel_paused_mail_subject', ['canal' => $canal]))
            ->greeting(__('messages.mail_greeting', ['nome' => $notifiable->name]))
            ->line(__('messages.channel_paused_banner', [
                'canal'  => "{$canal} (" . str_replace('whatsapp:', '', $this->channel->numero) . ')',
                'motivo' => __('messages.channel_pause_reason_' . $this->channel->pausa_motivo),
            ]))
            ->line(__('messages.channel_paused_mail_effect'))
            ->action(__('messages.channel_paused_manage'), route('empresa.edit') . '#whatsapp')
            ->salutation(__('messages.mail_salutation', ['app' => config('app.name')]));
    }
}
