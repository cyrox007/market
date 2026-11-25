<?php

namespace App\Services\Audit;

/** Process-local scope restored even when a queue job throws. No request body or model is serialized. */
final class AuditExecutionContext
{
    private static ?array $current = null;

    public static function current(): ?array { return self::$current; }

    public static function capture(string $source): ?array
    {
        $audit = app(ActivityAudit::class);
        if (! $audit->enabled()) { return null; }
        $context = $audit->context();
        // The external system is the actor, not the customer who triggered the job.
        return [
            'actor_type' => $source, 'source' => $source, 'actor_id' => null,
            'session_tag' => null, 'request_id' => $context['request_id'] ?? (string) \Illuminate\Support\Str::uuid(),
        ];
    }

    public static function run(array $context, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $context;
        try { return $callback(); }
        finally { self::$current = $previous; }
    }
}
