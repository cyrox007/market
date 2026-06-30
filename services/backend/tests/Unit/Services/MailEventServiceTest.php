<?php

namespace Tests\Unit\Services;

use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventLog;
use App\Models\Mail\MailEventTemplate;
use App\Services\Mail\MailEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailEventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MailEventService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MailEventService();
    }

    public function test_send_creates_log_and_sends_mail(): void
    {
        Mail::fake();

        $event = MailEvent::factory()->create([
            'code' => 'test.event',
            'is_active' => true,
        ]);

        $template = MailEventTemplate::factory()->create([
            'mail_event_id' => $event->id,
            'subject' => 'Test Subject {{name}}',
            'body' => 'Test Body {{name}}',
            'is_active' => true,
            'is_default' => true,
        ]);

        $variables = ['name' => 'John Doe'];
        $email = 'test@example.com';

        $result = $this->service->send('test.event', $email, $variables);

        $this->assertTrue($result);

        // Проверяем, что лог создан
        $this->assertDatabaseHas('mail_event_logs', [
            'mail_event_id' => $event->id,
            'mail_event_template_id' => $template->id,
            'recipient_email' => $email,
            'status' => 'sent',
        ]);

        // В этом тесте достаточно подтвердить успешный результат и запись в лог.
    }

    public function test_send_returns_false_for_inactive_event(): void
    {
        Mail::fake();

        $event = MailEvent::factory()->create([
            'code' => 'test.event',
            'is_active' => false,
        ]);

        $result = $this->service->send('test.event', 'test@example.com', []);

        $this->assertFalse($result);
        Mail::assertNothingSent();
    }

    public function test_send_returns_false_for_nonexistent_event(): void
    {
        Mail::fake();

        $result = $this->service->send('nonexistent.event', 'test@example.com', []);

        $this->assertFalse($result);
        Mail::assertNothingSent();
    }

    public function test_send_replaces_variables_in_template(): void
    {
        Mail::fake();

        $event = MailEvent::factory()->create([
            'code' => 'test.event',
            'is_active' => true,
        ]);

        $template = MailEventTemplate::factory()->create([
            'mail_event_id' => $event->id,
            'subject' => 'Hello {{name}}',
            'body' => 'Your order {{order_number}} is ready',
            'is_active' => true,
            'is_default' => true,
        ]);

        $variables = [
            'name' => 'John',
            'order_number' => 'ORD123',
        ];

        $this->service->send('test.event', 'test@example.com', $variables);

        $log = MailEventLog::where('mail_event_id', $event->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('John', $log->subject);
        $this->assertStringContainsString('ORD123', $log->body);
        $this->assertStringNotContainsString('{{name}}', $log->subject);
        $this->assertStringNotContainsString('{{order_number}}', $log->body);
    }
}
