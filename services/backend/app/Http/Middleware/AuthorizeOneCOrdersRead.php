<?php

namespace App\Http\Middleware;

use App\Services\Integration\OneCOrdersAccessTokens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dedicated machine credentials for the read-only 1C order export.
 * No administrator cookie, customer Sanctum token or outgoing 1C key.
 */
class AuthorizeOneCOrdersRead
{
    public function handle(Request $request, Closure $next): Response
    {
        // The proxy must correctly forward HTTPS; do not accept tokens over
        // plaintext transport in production even when the token is invalid.
        if (app()->isProduction() && ! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS required'], 403)
                ->header('Cache-Control', 'private, no-store');
        }

        $supplied = $request->header('X-1C-Orders-Token');
        if (! is_string($supplied) || strlen($supplied) < 32 || strlen($supplied) > 256) {
            return response()->json(['message' => 'Unauthorized'], 401)
                ->header('Cache-Control', 'private, no-store');
        }

        // Existing environment-managed token continues to work when explicitly
        // enabled; new admin-issued credentials are independent of that switch.
        $expected = (string) config('onec_orders_read.token', '');
        $legacy = (bool) config('onec_orders_read.enabled', false)
            && strlen($expected) >= 32
            && hash_equals(hash('sha256', $expected), hash('sha256', $supplied));

        $record = $legacy ? null : app(OneCOrdersAccessTokens::class)->authenticate($supplied, $request->ip());
        if (! $legacy && $record === null) {
            return response()->json(['message' => 'Unauthorized'], 401)
                ->header('Cache-Control', 'private, no-store');
        }

        if ($record !== null) {
            $request->attributes->set('onec_orders_access_token_id', $record->id);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
