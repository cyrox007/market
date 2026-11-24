<?php

namespace App\Jobs\Middleware;

use App\Services\Audit\AuditExecutionContext;

final class WithActivityAuditContext
{
    public function __construct(private string $source, private ?array $context = null) {}

    public function handle(object $job, callable $next): mixed
    {
        $context = $this->context ?? [
            'actor_type' => $this->source, 'source' => $this->source, 'actor_id' => null,
            'session_tag' => null, 'request_id' => (string) \Illuminate\Support\Str::uuid(),
        ];
        return AuditExecutionContext::run($context, fn () => $next($job));
    }
}
