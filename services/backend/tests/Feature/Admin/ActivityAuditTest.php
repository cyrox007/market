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

    public function test_only_explicitly_authorized_admin_can_view_or_export(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        $this->assertFalse(ActivityAudit::canView($admin));
        $admin->givePermissionTo('viewAny activity_audit');
        $this->assertTrue(ActivityAudit::canView($admin));
        $this->assertFalse(ActivityAudit::canExport($admin));
        $admin->givePermissionTo('export activity_audit');
        $this->assertTrue(ActivityAudit::canExport($admin));
        $admin->revokePermissionTo('viewAny activity_audit');
        $this->assertFalse(ActivityAudit::canExport($admin));

        $manager = User::factory()->create();
        Role::findOrCreate('manager', 'web');
        $manager->assignRole('manager');
        $manager->givePermissionTo(['viewAny activity_audit','export activity_audit']);
        $this->assertFalse(ActivityAudit::canView($manager));
        $this->assertFalse(ActivityAudit::canExport($manager));
    }

    public function test_export_is_bounded_filtered_and_audited(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        $audit = app(ActivityAudit::class);
        $audit->record('order', 'test.export_selected');
        $audit->record('security', 'test.export_excluded');
        $response = app(\App\Services\Audit\ActivityAuditExport::class)->download($admin,
            ActivityAuditEvent::query()->where('event_type', 'test.export_selected'));
        ob_start();
        try { $response->sendContent(); $csv = ob_get_contents(); }
        finally { ob_end_clean(); }
        $this->assertStringContainsString('test.export_selected', $csv);
        $this->assertStringNotContainsString('test.export_excluded', $csv);
        $row = ActivityAuditEvent::where('event_type', 'audit.export')->firstOrFail();
        $this->assertSame(1, $row->details['changed_count']);
        $this->assertEquals($admin->id, $row->actor_id);
    }

    public function test_settings_are_audited_and_critical_retention_is_bounded(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        $settings = app(\App\Services\Audit\ActivityAuditSettings::class);
        $defaults = config('activity_audit.retention_days');
        $settings->save($admin, ['behavior' => true, 'retention_days' => $defaults]);
        $this->assertTrue($settings->get()['behavior']);
        $this->assertDatabaseHas('activity_audit_events', ['event_type' => 'audit.settings_changed', 'actor_id' => $admin->id]);
        $defaults['security'] = 1;
        try {
            $settings->save($admin, ['behavior' => false, 'retention_days' => $defaults]);
            $this->fail('Unsafe retention was accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('retention_days.security', $e->errors());
        }
        $this->assertTrue($settings->get()['behavior']);
    }

    public function test_user_history_includes_only_unambiguous_prelogin_guest_activity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $audit = app(ActivityAudit::class);
        $ctx = ['actor_type'=>'guest','actor_id'=>null,'source'=>'storefront','session_tag'=>str_repeat('a',64),'request_id'=>null];
        $audit->record('shopping','test.guest_before',context:$ctx);
        $audit->record('security','auth.login',context:array_replace($ctx,['actor_type'=>'user','actor_id'=>$user->id]));
        $audit->record('shopping','test.guest_after',context:$ctx);
        $shared = array_replace($ctx,['session_tag'=>str_repeat('b',64)]);
        $audit->record('shopping','test.shared_guest',context:$shared);
        $audit->record('security','auth.login',context:array_replace($shared,['actor_type'=>'user','actor_id'=>$user->id]));
        $audit->record('security','auth.login',context:array_replace($shared,['actor_type'=>'user','actor_id'=>$other->id]));
        $events = \App\Services\Audit\ActivityAuditHistory::forUser(ActivityAuditEvent::query(),$user->id)->pluck('event_type')->all();
        $this->assertContains('test.guest_before',$events);
        $this->assertNotContains('test.guest_after',$events);
        $this->assertNotContains('test.shared_guest',$events);
    }

    public function test_behavior_job_is_idempotent_and_preserves_occurrence_time(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $when = now()->subMinute()->format('Y-m-d H:i:s.u');
        $context = ['actor_type'=>'guest','actor_id'=>null,'source'=>'storefront','session_tag'=>str_repeat('c',64),'request_id'=>null];
        $job = new \App\Jobs\RecordBehaviorAudit($id,$when,'catalog.search','success',200,$context);
        $this->assertSame('activity-audit',$job->queue);
        $job->handle(app(ActivityAudit::class));
        $job->handle(app(ActivityAudit::class));
        $this->assertSame(1,ActivityAuditEvent::where('event_id',$id)->count());
        $this->assertSame(substr($when,0,19),ActivityAuditEvent::where('event_id',$id)->firstOrFail()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_bulk_collection_audit_is_transactional(): void
    {
        $series = \App\Models\Product\ProductCollection::create(['name'=>'Audit series','slug'=>'audit-series','is_auto'=>false,'is_active'=>true,'priority'=>0,'limit'=>12]);
        $product = \App\Models\Product\Product::factory()->create(['state'=>'active']);
        DB::beginTransaction();
        try {
            app(\App\Services\Catalog\BulkCollectionMembership::class)->apply($series->id,[$product->id],'add');
            $row = ActivityAuditEvent::where('event_type','collection.bulk_add')->firstOrFail();
            $this->assertSame([$product->id],$row->details['product_ids']);
            $this->assertSame(1,$row->details['added_count']);
        } finally { DB::rollBack(); }
        $this->assertDatabaseMissing('activity_audit_events',['event_type'=>'collection.bulk_add']);
        $this->assertSame(0,$series->products()->count());
    }

    public function test_failed_audit_write_rolls_back_critical_model_even_if_caller_catches(): void
    {
        $user = User::factory()->create(['name'=>'Before audit']);
        $invalid = ['actor_type'=>'system','source'=>null,'actor_id'=>null,'session_tag'=>null,'request_id'=>null];
        try {
            \App\Services\Audit\AuditExecutionContext::run($invalid, fn () => $user->update(['name'=>'After audit']));
            $this->fail('Expected audit insert failure');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame('Before audit',$user->fresh()->name);
        }
        $this->assertNull(\App\Services\Audit\AuditExecutionContext::current());
    }

    public function test_job_scope_does_not_leak_and_correlates_events(): void
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $context = ['actor_type'=>'bank','source'=>'bank','actor_id'=>null,'session_tag'=>null,'request_id'=>$id];
        $middleware = new \App\Jobs\Middleware\WithActivityAuditContext('bank',$context);
        try {
            $middleware->handle(new \stdClass(), function () {
                app(ActivityAudit::class)->record('order','test.bank_context');
                throw new \RuntimeException('job failed');
            });
        } catch (\RuntimeException $e) { $this->assertSame('job failed',$e->getMessage()); }
        $this->assertDatabaseHas('activity_audit_events',['event_type'=>'test.bank_context','source'=>'bank','request_id'=>$id]);
        $this->assertNull(\App\Services\Audit\AuditExecutionContext::current());
        app(ActivityAudit::class)->record('order','test.after_job');
        $this->assertDatabaseHas('activity_audit_events',['event_type'=>'test.after_job','source'=>'system']);
    }

    public function test_timeline_has_server_side_access_checks(): void
    {
        $user = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
        $this->actingAs($user);
        Livewire::test(\App\Filament\Resources\ActivityAudit\Pages\UserActivityTimeline::class,['userId'=>$user->id])->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin','web'));
        $this->actingAs($admin);
        Livewire::test(\App\Filament\Resources\ActivityAudit\Pages\UserActivityTimeline::class,['userId'=>$user->id])->assertSuccessful()->assertSee('Хронология аккаунта');
        $this->assertDatabaseHas('activity_audit_events',['event_type'=>'audit.user_timeline_view','actor_id'=>$admin->id,'subject_id'=>$user->id]);
    }

    public function test_queue_health_detects_stale_backlog(): void
    {
        config(['queue_workers.connection'=>'database','queue.connections.database.connection'=>null]);
        DB::table('jobs')->insert(['queue'=>'activity-audit','payload'=>'{}','attempts'=>0,'reserved_at'=>null,'available_at'=>now()->subMinutes(10)->timestamp,'created_at'=>now()->subMinutes(10)->timestamp]);
        $health = app(\App\Services\Audit\ActivityAuditHealth::class)->inspect();
        $this->assertGreaterThanOrEqual(1,$health['pending']);
        $this->assertGreaterThanOrEqual(600,$health['oldest_seconds']);
        $this->assertFalse($health['healthy']);
        $this->artisan('audit:check-activity')->assertFailed();
    }

    public function test_behavior_batch_has_bounded_export_and_history_pagination(): void
    {
        $user = User::factory()->create();
        $context = ['actor_type'=>'user','source'=>'storefront','actor_id'=>$user->id,'session_tag'=>null,'request_id'=>null];
        for ($i=0;$i<120;$i++) {
            app(ActivityAudit::class)->record('behavior','test.batch',context:$context);
        }
        $query = \App\Services\Audit\ActivityAuditHistory::forUser(ActivityAuditEvent::query(),$user->id)->where('event_type','test.batch');
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(50);
        $this->assertCount(50,$page);
        $this->assertTrue($page->hasMorePages());
        $this->assertSame(120,(clone $query)->count());
    }

    public function test_export_rejects_more_than_5000_events_without_recording_success(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin','web'));
        $now = now()->format('Y-m-d H:i:s.u');
        for ($batch=0;$batch<11;$batch++) {
            $rows=[];
            for ($i=0;$i<($batch===10?1:500);$i++) {
                $rows[]=['event_id'=>(string)\Illuminate\Support\Str::uuid(),'category'=>'behavior','event_type'=>'test.large_export',
                    'actor_type'=>'system','source'=>'system','result'=>'success','details'=>'{}','created_at'=>$now];
            }
            DB::table('activity_audit_events')->insert($rows);
        }
        try {
            app(\App\Services\Audit\ActivityAuditExport::class)->download($admin,ActivityAuditEvent::where('event_type','test.large_export'));
            $this->fail('Oversized export was permitted');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422,$e->getStatusCode());
        }
        $this->assertDatabaseMissing('activity_audit_events',['event_type'=>'audit.export']);
    }
}
