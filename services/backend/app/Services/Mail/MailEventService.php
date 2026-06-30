<?php

namespace App\Services\Mail;

use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventLog;
use App\Models\Mail\MailEventTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailEventService
{
    /**
     * Send mail based on event code.
     *
     * @param string $eventCode Код события (например, 'order.created')
     * @param string $recipientEmail Email получателя
     * @param array<string, mixed> $variables Переменные для подстановки в шаблон
     * @param MailEventTemplate|null $template Конкретный шаблон (если не указан, используется default)
     * @return bool Успешно ли отправлено письмо
     */
    public function send(string $eventCode, string $recipientEmail, array $variables = [], ?MailEventTemplate $template = null): bool
    {
        try {
            // Находим событие
            $event = MailEvent::findByCode($eventCode);
            if (!$event || !$event->is_active) {
                Log::warning("Mail event not found or inactive: {$eventCode}");
                return false;
            }

            // Получаем шаблон
            if (!$template) {
                $template = $event->getActiveTemplate();
            }

            if (!$template || !$template->is_active) {
                Log::warning("Mail template not found or inactive for event: {$eventCode}");
                return false;
            }

            // Заменяем переменные в шаблоне
            $replaced = $template->replaceVariables($variables);
            $subject = $replaced['subject'];
            $body = $replaced['body'];

            // Создаем лог перед отправкой
            $log = MailEventLog::create([
                'mail_event_id' => $event->id,
                'mail_event_template_id' => $template->id,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body' => $body,
                'status' => 'pending',
                'variables' => $variables,
            ]);

            // Отправляем письмо
            try {
                Mail::raw($body, function ($message) use ($recipientEmail, $subject) {
                    $message->to($recipientEmail)
                        ->subject($subject);
                });

                // Помечаем как отправленное
                $log->markAsSent();

                return true;
            } catch (\Exception $e) {
                // Помечаем как неудачное
                $log->markAsFailed($e->getMessage());
                Log::error("Failed to send mail for event {$eventCode}: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Error in MailEventService::send for event {$eventCode}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Send mail using Mailable class with event template.
     *
     * @param string $eventCode Код события
     * @param string $recipientEmail Email получателя
     * @param \Illuminate\Mail\Mailable $mailable Mailable класс
     * @param array<string, mixed> $variables Переменные для подстановки
     * @return bool Успешно ли отправлено письмо
     */
    public function sendMailable(string $eventCode, string $recipientEmail, $mailable, array $variables = []): bool
    {
        try {
            // Находим событие
            $event = MailEvent::findByCode($eventCode);
            if (!$event || !$event->is_active) {
                Log::warning("Mail event not found or inactive: {$eventCode}");
                return false;
            }

            // Получаем шаблон
            $template = $event->getActiveTemplate();
            if (!$template || !$template->is_active) {
                Log::warning("Mail template not found or inactive for event: {$eventCode}");
                return false;
            }

            // Заменяем переменные в шаблоне
            $replaced = $template->replaceVariables($variables);
            $subject = $replaced['subject'];
            $body = $replaced['body'];

            // Устанавливаем subject и body в mailable
            $mailable->subject($subject);
            if (method_exists($mailable, 'setBody')) {
                $mailable->setBody($body);
            }

            // Создаем лог перед отправкой
            $log = MailEventLog::create([
                'mail_event_id' => $event->id,
                'mail_event_template_id' => $template->id,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body' => $body,
                'status' => 'pending',
                'variables' => $variables,
            ]);

            // Отправляем письмо
            try {
                Mail::to($recipientEmail)->send($mailable);

                // Помечаем как отправленное
                $log->markAsSent();

                return true;
            } catch (\Exception $e) {
                // Помечаем как неудачное
                $log->markAsFailed($e->getMessage());
                Log::error("Failed to send mailable for event {$eventCode}: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Error in MailEventService::sendMailable for event {$eventCode}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Get available variables for event.
     *
     * @param string $eventCode Код события
     * @return array<string> Список доступных переменных
     */
    public function getAvailableVariables(string $eventCode): array
    {
        $event = MailEvent::findByCode($eventCode);
        if (!$event) {
            return [];
        }

        $template = $event->getActiveTemplate();
        if (!$template || !$template->variables) {
            return [];
        }

        return $template->variables;
    }
}
