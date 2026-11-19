<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountEmailChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $email)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Почта аккаунта изменена')->greeting('Здравствуйте!')
            ->line('Подтверждён новый адрес вашего аккаунта: '.$this->email)
            ->line('Если вы не меняли почту, обратитесь в поддержку магазина.');
    }
}
