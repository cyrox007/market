<?php
namespace Tests\Feature\Api;

use App\Events\OrderStatusChanged;
use App\Jobs\SendPromotionalMail;
use App\Listeners\Mail\SendOrderStatusChangedMail;
use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventLog;
use App\Models\Mail\MailEventTemplate;
use App\Models\Newsletter\NewsletterSubscriber;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Mail\MailEventService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function event(string $code = 'promo.one', string $category = 'promotion'): MailEvent
    {
        $event = MailEvent::factory()->create(compact('code', 'category'));
        MailEventTemplate::factory()->create(['mail_event_id' => $event->id, 'is_default' => true]);
        return $event;
    }

    public function test_settings_defaults_and_partial_update_preserve_legacy_flags_and_sync_subscription(): void
    {
        $user = User::factory()->create(['push_notifications' => true]);
        $this->actingAs($user)->getJson('/api/v1/auth/notification-settings')->assertOk()
            ->assertJsonPath('settings.email_order_notifications', true)->assertJsonPath('settings.email_promotions', false);
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/notification-settings', ['email_promotions' => true, 'email_order_notifications' => false])->assertOk()
            ->assertJsonPath('settings.push_notifications', true)->assertJsonPath('settings.email_order_notifications', false);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => $user->email, 'is_active' => true]);
        $this->actingAs($user, 'web')->putJson('/api/v1/auth/notification-settings', ['email_promotions' => false])->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => $user->email, 'is_active' => false]);
    }

    public function test_status_opt_out_does_not_block_creation_and_guest_status_emails(): void
    {
        $user = User::factory()->create(['email_order_notifications' => false]);
        $this->event('order.status_changed', 'transactional');
        $this->event('order.created', 'transactional');
        $mail = app(MailEventService::class);
        $this->assertFalse($mail->send('order.status_changed', $user->email));
        $this->assertTrue($mail->send('order.created', $user->email));
        $this->assertTrue($mail->send('order.status_changed', 'guest@example.test'));
        $order = Order::factory()->create(['user_id' => $user->id, 'contact_email' => 'different@example.test']);
        $before = MailEventLog::count();
        app(SendOrderStatusChangedMail::class)->handle(new OrderStatusChanged($order, 'new', 'accepted'));
        $this->assertSame($before, MailEventLog::count());
    }

    public function test_weekly_limit_is_shared_between_promotions_and_persists_when_resubscribing(): void
    {
        $user = User::factory()->create(['email_promotions' => true]);
        $first = $this->event();
        $second = $this->event('promo.two');
        $mail = app(MailEventService::class);
        $this->assertTrue($mail->send($first->code, $user->email));
        $this->assertFalse($mail->send($second->code, $user->email));
        $user->update(['email_promotions' => false]);
        $user->update(['email_promotions' => true]);
        $this->assertFalse($mail->send($second->code, $user->email));
        $this->travel(7)->days();
        $this->assertTrue($mail->send($second->code, $user->email));
        $this->assertSame(2, MailEventLog::where('status', 'sent')->count());
        $this->assertStringContainsString('Отписаться', MailEventLog::first()->body);
    }

    public function test_send_checks_current_consent_and_inactive_event_when_job_is_executed(): void
    {
        $user = User::factory()->create(['email_promotions' => true]);
        $event = $this->event();
        $subscriber = NewsletterSubscriber::where('email', $user->email)->firstOrFail();
        $job = new SendPromotionalMail($event->id, $subscriber->id);
        $user->update(['email_promotions' => false]);
        $job->handle(app(MailEventService::class));
        $this->assertSame(0, MailEventLog::count());
        $user->update(['email_promotions' => true]);
        $event->update(['is_active' => false]);
        $job->handle(app(MailEventService::class));
        $this->assertSame(0, MailEventLog::count());
    }

    public function test_guest_subscription_and_unsubscribe_require_a_valid_link_and_explicit_confirmation(): void
    {
        $event = $this->event();
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'guest@example.test'])->assertCreated();
        $mail = app(MailEventService::class);
        $this->assertTrue($mail->send($event->code, 'guest@example.test'));
        $url = URL::signedRoute('newsletter.unsubscribe', ['email' => 'guest@example.test']);
        $this->get($url)->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'guest@example.test', 'is_active' => true]);
        $this->postJson($url)->assertOk();
        $this->travel(8)->days();
        $this->assertFalse($mail->send($event->code, 'guest@example.test'));
        $this->postJson('/api/v1/newsletter/unsubscribe?email=guest@example.test')->assertForbidden();
    }

    public function test_anonymous_subscription_cannot_override_registered_users_opt_out(): void
    {
        $user = User::factory()->create(['email_promotions' => false]);
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => $user->email])->assertUnprocessable();
        $this->assertFalse($user->fresh()->email_promotions);
        $this->assertFalse(NewsletterSubscriber::create(['email' => 'inactive@example.test', 'is_active' => false])->is_active);
    }

    public function test_failed_or_unconfigured_delivery_does_not_consume_weekly_limit(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'guest@example.test', 'is_active' => true]);
        $event = $this->event();
        $event->templates()->update(['is_active' => false]);
        $this->assertFalse(app(MailEventService::class)->send($event->code, $subscriber->email));
        $this->assertDatabaseHas('mail_promotion_limits', ['recipient_hash' => hash('sha256', $subscriber->email), 'last_sent_at' => null]);
        $event->templates()->update(['is_active' => true]);
        $this->assertTrue(app(MailEventService::class)->send($event->code, $subscriber->email));
    }

    public function test_admin_subscription_change_and_email_change_keep_profile_in_sync(): void
    {
        $user = User::factory()->create(['email_promotions' => true]);
        $subscriber = NewsletterSubscriber::where('email', $user->email)->firstOrFail();
        $subscriber->update(['is_active' => false]);
        $this->assertFalse($user->fresh()->email_promotions);
        $user->refresh()->update(['email_promotions' => true]);
        $old = $user->email;
        $user->update(['email' => 'new@example.test']);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => $old, 'is_active' => false]);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'new@example.test', 'is_active' => true]);
    }
    public function test_mailable_cannot_bypass_promotion_limit(): void
    {
        $user = User::factory()->create(['email_promotions' => true]);
        $event = $this->event();
        $mail = app(MailEventService::class);
        $this->assertTrue($mail->send($event->code, $user->email));
        $this->assertFalse($mail->sendMailable($event->code, $user->email, new \Illuminate\Mail\Mailable()));
    }

    public function test_admin_bulk_action_queues_only_selected_recipients(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'web');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        \Illuminate\Support\Facades\Queue::fake();
        $subscriber = NewsletterSubscriber::create(['email' => 'selected@example.test', 'is_active' => true]);
        NewsletterSubscriber::create(['email' => 'unselected@example.test', 'is_active' => true]);
        $event = $this->event();
        \Livewire\Livewire::test(\App\Filament\Resources\Newsletter\Pages\ListNewsletterSubscribers::class)
            ->callTableBulkAction('sendPromotion', [$subscriber], data: ['event_id' => $event->id])->assertHasNoActionErrors();
        \Illuminate\Support\Facades\Queue::assertPushed(SendPromotionalMail::class, fn ($job) => $job->subscriberId === $subscriber->id && $job->eventId === $event->id);
        \Illuminate\Support\Facades\Queue::assertPushed(SendPromotionalMail::class, 1);
    }

    public function test_deleting_and_recreating_subscriber_does_not_reset_delivery_limit(): void
    {
        $event = $this->event();
        $subscriber = NewsletterSubscriber::create(['email' => 'guest@example.test', 'is_active' => true]);
        $mail = app(MailEventService::class);
        $this->assertTrue($mail->send($event->code, $subscriber->email));
        $subscriber->delete();
        NewsletterSubscriber::create(['email' => 'guest@example.test', 'is_active' => true]);
        $this->assertFalse($mail->send($event->code, 'guest@example.test'));
    }

    public function test_transport_error_is_retryable_and_keeps_failed_log_without_consuming_limit(): void
    {
        $event = $this->event();
        $subscriber = NewsletterSubscriber::create(['email' => 'guest@example.test', 'is_active' => true]);
        $originalMailer = Mail::getFacadeRoot();
        Mail::swap(\Mockery::mock(\Illuminate\Contracts\Mail\Mailer::class));
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Temporary mail failure'));
        try {
            (new SendPromotionalMail($event->id, $subscriber->id))->handle(app(MailEventService::class));
            $this->fail('Transport errors must reach the queue worker.');
        } catch (\App\Services\Mail\MailDeliveryException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
        $this->assertDatabaseHas('mail_event_logs', ['status' => 'failed', 'recipient_email' => $subscriber->email]);
        $this->assertDatabaseHas('mail_promotion_limits', ['recipient_hash' => hash('sha256', $subscriber->email), 'last_sent_at' => null]);
        Mail::swap($originalMailer);
        $this->assertTrue(app(MailEventService::class)->send($event->code, $subscriber->email));
    }

}
