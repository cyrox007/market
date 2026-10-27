<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OperatorDiagnosticsController
{
    public function __invoke(Request $request): Response
    {
        abort_unless(config('operator_diagnostics.enabled')
            && strtotime((string) config('operator_diagnostics.until')) > time(), 404);
        abort_unless($request->user('web')?->canAccessPanel(\Filament\Facades\Filament::getPanel('admin_sv')), 403);
        $data = $request->validate([
            'events' => ['required', 'array', 'max:20'],
            'events.*.id' => ['required', 'uuid'],
            'events.*.event' => ['required', Rule::in(['heartbeat_http', 'heartbeat_invalid_json', 'heartbeat_user_mismatch', 'heartbeat_missing_token', 'heartbeat_network', 'mutation_http', 'mutation_network', 'upload_start', 'upload_finish', 'upload_error', 'upload_http'])],
            'events.*.time' => ['required', 'integer', 'min:0', 'max:9999999999999'],
            'events.*.status' => ['nullable', 'integer', 'between:0,599'],
            'events.*.duration_ms' => ['nullable', 'integer', 'between:0,3600000'],
            'events.*.request_id' => ['nullable', 'uuid'],
            'events.*.redirected' => ['nullable', 'boolean'],
            'events.*.json' => ['nullable', 'boolean'],
        ]);
        foreach ($data['events'] as $event) {
            // Explicit allowlist: never log arbitrary event fields supplied by a browser.
            $safe = array_intersect_key($event, array_flip(['id', 'event', 'time', 'status', 'duration_ms', 'request_id', 'redirected', 'json']));
            Log::channel('operator_diagnostics')->info('operator_browser', $safe + [
                'session_tag' => substr(hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')), 0, 20),
            ]);
        }
        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }
}
