<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ActivityAudit\ActivityAuditResource;
use App\Filament\Resources\ActivityAudit\Pages\ListActivityAudit;
use App\Models\ActivityAuditEvent;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Audit\ActivityAudit;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['activity_audit.enabled' => true]);
    }

    public function test_only_super_admin_can_open_journal(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('manager', 'web');
        $user->assignRole('manager');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $this->assertFalse(ActivityAuditResource::canViewAny());
        Livewire::test(ListActivityAudit::class)->assertForbidden();

        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        $this->assertTrue(ActivityAuditResource::canViewAny());
        Livewire::test(ListActivityAudit::class)->assertSuccessful();
        $this->assertDatabaseHas('activity_audit_events', ['event_type' => 'audit.view', 'actor_id' => $admin->id]);
        $record = ActivityAuditEvent::query()->firstOrFail();
        $this->assertFalse(ActivityAuditResource::canCreate());
        $this->assertFalse(ActivityAuditResource::canEdit($record));
        $this->assertFalse(ActivityAuditResource::canDelete($record));
    }

    public function test_audit_does_not_store_credentials_or_arbitrary_fields(): void
    {
        app(ActivityAudit::class)->record('security', 'test.safe', details: [
            'password' => 'secret-password', 'token' => 'secret-token',
            'fields' => ['password', 'untrusted-field'],
            'changes' => ['password' => ['old-secret', 'new-secret'], 'price' => [100, 200]],
            'http_status' => 200,
        ]);
        $row = ActivityAuditEvent::where('event_type', 'test.safe')->firstOrFail();
        $this->assertSame(['password'], $row->details['fields']);
        $this->assertSame([100, 200], $row->details['changes']['price']);
        $this->assertStringNotContainsString('secret', json_encode($row->details));
        $this->assertArrayNotHasKey('token', $row->details);
    }

    public function test_order_audit_rolls_back_with_business_transaction(): void
    {
        $order = Order::factory()->create(['status' => 'assembling', 'payment_method' => null, 'cooling_until' => now()->subHour()]);
        DB::beginTransaction();
        try {
            $order->update(['status' => 'assembled']);
            $this->assertDatabaseHas('activity_audit_events', ['event_type' => 'order.updated', 'subject_id' => $order->id]);
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseMissing('activity_audit_events', ['event_type' => 'order.updated', 'subject_id' => $order->id]);
        $this->assertSame('assembling', $order->fresh()->status);
    }

    public function test_pruning_respects_category_and_limit(): void
    {
        $audit = app(ActivityAudit::class);
        $audit->record('behavior', 'test.old');
        $audit->record('behavior', 'test.recent');
        $audit->record('security', 'test.security');
        DB::table('activity_audit_events')->whereIn('event_type', ['test.old', 'test.security'])->update(['created_at' => now()->subDays(45)]);
        $this->artisan('audit:prune-activity', ['--limit' => 1])->assertSuccessful();
        $this->assertDatabaseMissing('activity_audit_events', ['event_type' => 'test.old']);
        $this->assertDatabaseHas('activity_audit_events', ['event_type' => 'test.recent']);
        $this->assertDatabaseHas('activity_audit_events', ['event_type' => 'test.security']);
    }

    public function test_disabled_audit_does_not_record(): void
    {
        config(['activity_audit.enabled' => false]);
        app(ActivityAudit::class)->record('security', 'test.disabled');
        $this->assertDatabaseMissing('activity_audit_events', ['event_type' => 'test.disabled']);
    }

    public function test_failed_login_records_outcome_without_password_or_email(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'not-a-real-password']);
        $row = ActivityAuditEvent::where('event_type', 'auth.login')->firstOrFail();
        $this->assertSame('denied', $row->result);
        $this->assertNotNull($row->request_id);
        $this->assertSame('storefront', $row->source);
        $this->assertStringNotContainsString('missing@example.test', json_encode($row->toArray()));
        $this->assertStringNotContainsString('not-a-real-password', json_encode($row->toArray()));
    }
}
