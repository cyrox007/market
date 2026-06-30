<?php

namespace App\Listeners\Mail;

use App\Events\UserAutoRegistered;
use App\Mail\UserRegisteredMail;
use App\Services\Mail\MailEventService;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель события автоматической регистрации пользователя
 * Отправляет письмо с данными аккаунта
 */
class SendUserRegisteredMail
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(UserAutoRegistered $event): void
    {
        try {
            $user = $event->getUser();
            $password = $event->getPassword();

            // Проверяем, есть ли email для отправки
            if (!$user->email) {
                Log::warning("SendUserRegisteredMail: No email for user", [
                    'user_id' => $user->id,
                ]);
                return;
            }

            // Подготавливаем переменные для шаблона
            $variables = $this->prepareVariables($user, $password);

            // Создаем Mailable
            $mailable = new UserRegisteredMail($user, $password);

            // Отправляем письмо через сервис
            $this->mailEventService->sendMailable(
                'user.registered',
                $user->email,
                $mailable,
                $variables
            );

            Log::info("SendUserRegisteredMail: Mail sent for user", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error("SendUserRegisteredMail: Error sending mail", [
                'user_id' => $event->getUser()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Prepare variables for template.
     *
     * @return array<string, mixed>
     */
    protected function prepareVariables($user, string $password): array
    {
        return [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_phone' => $user->phone ?? 'Не указан',
            'password' => $password,
            'login_url' => url('/login'),
        ];
    }
}
