<?php
namespace App\Services\Mail;

use App\Models\Mail\MailEvent;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MailDeliveryPolicy
{
    public function deliver(string $code, string $email, callable $send): bool
    {
        $email = strtolower(trim($email));
        if ($code === 'order.status_changed' && User::where('email', $email)->where('email_order_notifications', false)->exists()) {
            return false;
        }
        if (MailEvent::where('code', $code)->value('category') !== 'promotion') {
            return $send();
        }
        // Serialize all promotional events for one recipient, including retries and parallel workers.
        $result = DB::transaction(function () use ($email, $send): bool|MailDeliveryException {
            NewsletterSubscriber::query()->insertOrIgnore([
                'email' => $email, 'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $recipientHash = hash('sha256', $email);
            DB::table('mail_promotion_limits')->insertOrIgnore(['recipient_hash' => $recipientHash]);
            $limit = DB::table('mail_promotion_limits')->where('recipient_hash', $recipientHash)->lockForUpdate()->first();
            $subscriber = NewsletterSubscriber::where('email', $email)->firstOrFail();
            $user = User::where('email', $email)->first();
            $optedIn = $user ? (bool) $user->email_promotions : $subscriber->is_active;
            if (! $optedIn || ($limit->last_sent_at && \Illuminate\Support\Carbon::parse($limit->last_sent_at)->gt(now()->subDays(7)))) {
                return false;
            }
            try {
                if (! $send()) {
                    return false;
                }
            } catch (MailDeliveryException $e) {
                // Commit the failed-attempt log, then let the queue retry outside this transaction.
                return $e;
            }
            DB::table('mail_promotion_limits')->where('recipient_hash', $recipientHash)->update(['last_sent_at' => now()]);
            return true;
        });
        if ($result instanceof MailDeliveryException) {
            throw $result;
        }
        return $result;
    }
}
