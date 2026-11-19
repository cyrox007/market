<?php
namespace App\Jobs;

use App\Models\Mail\MailEvent;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Services\Mail\MailEventService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPromotionalMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public int $eventId, public int $subscriberId)
    {
        $this->onQueue('default');
    }

    public function handle(MailEventService $mail): void
    {
        $event = MailEvent::whereKey($this->eventId)->where('category', 'promotion')->where('is_active', true)->first();
        $subscriber = NewsletterSubscriber::find($this->subscriberId);
        if ($event && $subscriber) {
            // Consent and frequency are rechecked when delivered.
            $mail->send($event->code, $subscriber->email, ['recipient_email' => $subscriber->email]);
        }
    }
}
