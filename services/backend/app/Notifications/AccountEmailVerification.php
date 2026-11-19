<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountEmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token, public readonly bool $change)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        return (new MailMessage)->subject($this->change ? 'Подтвердите новую почту' : 'Подтвердите почту')
            ->greeting('Здравствуйте!')
            ->line($this->change ? 'Подтвердите новый адрес для вашего аккаунта.' : 'Подтвердите адрес вашей электронной почты.')
            ->action('Подтвердить почту', $base.'/verify-email#token='.rawurlencode($this->token))
            ->line('Ссылка действует 60 минут. Если вы не запрашивали это письмо, ничего не нажимайте.');
    }
}
