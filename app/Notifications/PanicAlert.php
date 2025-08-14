<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PanicAlert extends Notification
{
    use Queueable;

    public function __construct(public string $userEmail, public string $timeIso) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Panic Alert')
            ->line('Panic code was triggered.')
            ->line('User: ' . $this->userEmail)
            ->line('Time: ' . $this->timeIso);
    }
}
