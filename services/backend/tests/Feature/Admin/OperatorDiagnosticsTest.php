<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\DiagnoseOperatorRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OperatorDiagnosticsTest extends TestCase
{
    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['operator_diagnostics.enabled' => true, 'operator_diagnostics.until' => now()->addDay()->toIso8601String()]);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->andReturnUsing(function ($event, $context) { $this->entries[] = [$event, $context]; });
        Log::shouldReceive('channel')->with('operator_diagnostics')->andReturn($logger);
    }

    public function test_middleware_records_final_status_without_request_secrets(): void
    {
        $request = Request::create('/admin_sv/session-status?secret=secret-query', 'GET', ['password' => 'secret-password']);
        $request->headers->set('Cookie', 'secret-cookie');
        $request->headers->set('Authorization', 'Bearer secret-auth');
        $request->setUserResolver(fn () => null);
        $middleware = new DiagnoseOperatorRequests;
        $response = $middleware->handle($request, fn () => new Response('', 401));
        $middleware->terminate($request, $response);
        $this->assertTrue($response->headers->has('X-Operator-Request-ID'));
        $this->assertCount(2, $this->entries);
        $this->assertSame(401, $this->entries[1][1]['status']);
        $this->assertFalse($this->entries[1][1]['authenticated']);
        $this->assertStringNotContainsString('secret-', json_encode($this->entries));
    }

    public function test_expired_diagnostics_and_unrelated_paths_are_not_logged(): void
    {
        $middleware = new DiagnoseOperatorRequests;
        foreach (['/api/v1/products', '/livewire/upload-file'] as $path) {
            if ($path === '/livewire/upload-file') config(['operator_diagnostics.until' => now()->subDay()->toIso8601String()]);
            $request = Request::create($path);
            $response = $middleware->handle($request, fn () => new Response);
            $middleware->terminate($request, $response);
        }
        $this->assertSame([], $this->entries);
    }

    public function test_browser_report_requires_an_operator(): void
    {
        $this->postJson('/admin_sv/operator-diagnostics', ['events' => []])->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web')->postJson('/admin_sv/operator-diagnostics', ['events' => []])->assertForbidden();
    }

    public function test_rendered_error_is_recorded_without_exception_details(): void
    {
        $middleware = new DiagnoseOperatorRequests;
        $request = Request::create('/livewire/update', 'POST');
        $request->setUserResolver(fn () => null);
        try {
            $middleware->handle($request, fn () => throw new \RuntimeException('secret-file-or-query'));
            $this->fail('Exception must propagate');
        } catch (\RuntimeException) {
            $middleware->terminate($request, new Response('', 500));
        }
        $this->assertSame(500, $this->entries[1][1]['status']);
        $this->assertSame(\RuntimeException::class, $this->entries[1][1]['exception_class']);
        $this->assertStringNotContainsString('secret-', json_encode($this->entries));
    }

    public function test_browser_report_logs_only_allowlisted_fields(): void
    {
        Role::findOrCreate('manager', 'web');
        $user = User::factory()->create(); $user->assignRole('manager');
        $this->actingAs($user, 'web')->postJson('/admin_sv/operator-diagnostics', ['events' => [[
            'id' => (string) \Illuminate\Support\Str::uuid(), 'event' => 'heartbeat_network', 'time' => 1793100000000,
            'duration_ms' => 1200, 'filename' => 'secret-filename', 'form' => 'secret-form',
        ]]])->assertNoContent();
        $this->assertCount(1, $this->entries);
        $this->assertSame('heartbeat_network', $this->entries[0][1]['event']);
        $this->assertStringNotContainsString('secret-', json_encode($this->entries));
    }
}
