<?php

namespace Tests\Feature\Shipping;

use App\Events\OrderCreated;
use App\Filament\Resources\Mail\Pages\ListMailTemplates;
use App\Listeners\Mail\SendOrderCreatedMail;
use App\Mail\OrderCreatedMail;
use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventLog;
use App\Models\Mail\MailEventTemplate;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Mail\MailTemplateContent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestMailTemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_variables_are_text_and_are_not_expanded_recursively(): void
    {
        $template = new MailEventTemplate([
            'subject' => 'Заказ {{order_number}}',
            'body' => '<p>{{contact_name}}</p><p>{{order_number}}</p>',
        ]);
        $result = $template->replaceVariables([
            'contact_name' => '<a href="https://evil.test">Оплатить</a> {{order_number}}',
            'order_number' => '123',
        ], html: true);
        $this->assertStringNotContainsString('<a href=', $result['body']);
        $this->assertStringContainsString('&lt;a href=', $result['body']);
        $this->assertStringContainsString('{{order_number}}', $result['body']);
        $this->assertSame('Заказ 123', $result['subject']);

        $template->body = 'Здравствуйте, {{contact_name}}!';
        $plain = $template->replaceVariables(['contact_name' => '<b>Имя</b>'], html: true);
        $this->assertStringContainsString('&lt;b&gt;Имя&lt;/b&gt;', MailTemplateContent::html($plain['body']));
    }

    public function test_all_editor_mailables_render_both_html_and_text(): void
    {
        $order = $this->guestOrder();
        $user = User::factory()->create();
        $mailables = [
            new OrderCreatedMail($order),
            new \App\Mail\OrderStatusChangedMail($order, 'Новый', 'Собирается'),
            new \App\Mail\OrderCancelledMail($order),
            new \App\Mail\UserRegisteredMail($user, 'test-password'),
        ];
        foreach ($mailables as $mail) {
            $mail->setBody('<p>Письмо <strong>покупателю</strong></p>');
            $mail->assertSeeInHtml('<strong>покупателю</strong>', escape: false);
            $mail->assertSeeInText('Письмо');
            $mail->assertDontSeeInText('<strong>');
        }
    }

    private function guestOrder(): Order
    {
        return Order::factory()->create([
            'user_id' => null,
            'guest_token_hash' => hash('sha256', str_repeat('a', 64)),
            'contact_email' => 'guest@example.test',
            'contact_name' => 'Александр',
        ]);
    }

    public function test_guest_template_is_available_after_migrations_and_seeding_is_idempotent(): void
    {
        $event = MailEvent::where('code', 'order.created.guest')->firstOrFail();
        $template = $event->getActiveTemplate();

        $this->assertNotNull($template);
        $this->assertTrue($template->is_default);
        $this->assertStringContainsString('{{order_number}}', $template->body);

        $template->update(['subject' => 'Индивидуальный текст']);
        $this->seed(\Database\Seeders\MailEventsSeeder::class);

        $this->assertSame('Индивидуальный текст', $template->fresh()->subject);
        $this->assertSame(1, $event->templates()->count());
    }

    public function test_editor_html_is_sanitized_and_plain_text_is_readable(): void
    {
        $html = '<p>Заказ <strong>№100</strong><script>steal()</script></p>'
            .'<a href="javascript:alert(1)" onclick="bad()">Опасная ссылка</a>'
            .'<a href="https://example.test/orders/1" onclick="bad()">Безопасная ссылка</a>'
            .'<img src=x onerror=bad()>';
        $rendered = MailTemplateContent::html($html);

        $this->assertStringContainsString('<strong>№100</strong>', $rendered);
        $this->assertStringContainsString('href="https://example.test/orders/1"', $rendered);
        $this->assertStringNotContainsString('script', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->assertStringNotContainsString('onclick', $rendered);
        $this->assertStringNotContainsString('<img', $rendered);
        $this->assertStringContainsString('Заказ', MailTemplateContent::plainText($html));
        $this->assertStringNotContainsString('<strong>', MailTemplateContent::plainText($html));
    }

    public function test_guest_creation_uses_guest_template_and_access_link_is_not_logged(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://shop.test']);
        $event = MailEvent::where('code', 'order.created.guest')->firstOrFail();
        $event->getActiveTemplate()->update([
            'body' => '<p>Шаблон гостя: {{order_number}}, {{contact_name}}</p>',
        ]);
        $order = $this->guestOrder();

        app(SendOrderCreatedMail::class)->handle(new OrderCreated($order));

        Mail::assertSent(OrderCreatedMail::class, function (OrderCreatedMail $mail) use ($order): bool {
            $mail->assertSeeInText('Шаблон гостя: '.$order->number.', Александр');
            $mail->assertSeeInHtml('#access=');
            return true;
        });
        $log = MailEventLog::firstOrFail();
        $this->assertSame($event->id, $log->mail_event_id);
        $this->assertStringNotContainsString('#access=', json_encode($log->toArray()));
    }

    public function test_auto_registered_checkout_still_uses_guest_notification(): void
    {
        Mail::fake();
        $registered = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $registered->id,
            'guest_checkout' => true,
            'guest_token_hash' => hash('sha256', str_repeat('b', 64)),
            'contact_email' => 'guest@example.test',
        ]);

        $event = MailEvent::where('code', 'order.created.guest')->firstOrFail();
        app(SendOrderCreatedMail::class)->handle(new OrderCreated($order));

        Mail::assertSent(OrderCreatedMail::class);
        $this->assertSame($event->id, MailEventLog::firstOrFail()->mail_event_id);
    }

    public function test_template_editor_has_own_navigation_and_requires_mail_event_permission(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));

        $this->assertSame('Шаблоны писем', \App\Filament\Resources\Mail\MailTemplateResource::getNavigationLabel());

        Livewire::test(ListMailTemplates::class)
            ->assertStatus(200)
            ->assertSee('Подтверждение гостевого заказа');
    }
}
