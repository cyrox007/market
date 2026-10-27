<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DiagnoseOperatorRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $until = config('operator_diagnostics.until');
        if (! config('operator_diagnostics.enabled') || ! is_string($until)
            || ! ($expires = strtotime($until)) || $expires <= time()
            || ! $request->is('admin_sv/session-status', 'livewire/update', 'livewire/upload-file')) {
            return $next($request);
        }

        $request->attributes->set('operator_diagnostic', [
            'request_id' => (string) Str::uuid(),
            'path' => $request->path(),
            'method' => $request->method(),
            'action' => in_array($request->header('X-Operator-Action'), ['upload', 'mutation', 'background'], true)
                ? $request->header('X-Operator-Action') : 'unknown',
            'content_length' => max(0, (int) $request->server('CONTENT_LENGTH', 0)),
            'has_session_cookie' => $request->cookies->has((string) config('session.cookie')),
            'started_at' => microtime(true),
        ]);
        $this->write($request, 'started');

        try {
            $response = $next($request);
            $response->headers->set('X-Operator-Request-ID', $request->attributes->get('operator_diagnostic')['request_id']);
            return $response;
        } catch (Throwable $exception) {
            // Only the class: exception messages can contain SQL, input or credentials.
            $request->attributes->set('operator_diagnostic_exception', $exception::class);
            throw $exception;
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->has('operator_diagnostic')) {
            $this->write($request, 'finished', $response->getStatusCode());
        }
    }

    private function write(Request $request, string $event, ?int $status = null): void
    {
        try {
            $context = $request->attributes->get('operator_diagnostic');
            $started = $context['started_at'];
            unset($context['started_at']);
            $context['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
            $context['php_elapsed_ms'] = (int) round((microtime(true) - (float) $request->server('REQUEST_TIME_FLOAT', $started)) * 1000);
            $context['status'] = $status;
            if ($event === 'finished') {
                $user = $request->user('web');
                $context['authenticated'] = $user !== null;
                $expected = $request->header('X-Operator-Expected-User');
                $context['expected_user_matches'] = is_string($expected) && ctype_digit($expected)
                    ? ($user !== null && hash_equals((string) $user->getAuthIdentifier(), $expected)) : null;
                $context['has_session'] = $request->hasSession();
                $context['session_tag'] = $request->hasSession()
                    ? substr(hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')), 0, 20) : null;
                $context['exception_class'] = $request->attributes->get('operator_diagnostic_exception');
                $context['session_driver'] = config('session.driver');
                $context['lock_wait_ms'] = $request->attributes->get('operator_lock_wait_ms');
                $context['lock_acquired'] = $request->attributes->get('operator_lock_acquired');
            }
            Log::channel('operator_diagnostics')->info('operator_request_'.$event, $context);
        } catch (Throwable) {
            // Diagnostics must not cause a failed upload if the log storage is unavailable.
        }
    }
}
