<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dedicated service credential for read-only 1C order exports.
 * No browser session, administrator role or outgoing integration key is accepted.
 */
class AuthorizeOneCOrdersRead
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('onec_orders_read.token', '');
        $supplied = $request->header('X-1C-Orders-Token');

        if (! (bool) config('onec_orders_read.enabled', false) || strlen($expected) < 32
            || ! is_string($supplied) || $supplied === ''
            || ! hash_equals(hash('sha256', $expected), hash('sha256', $supplied))) {
            return response()->json(['message' => 'Unauthorized'], 401)
                ->header('Cache-Control', 'private, no-store');
        }

        // Never expose customer contact details over plain HTTP in production.
        // HTTPS termination must be configured correctly on the reverse proxy.
        if (app()->isProduction() && ! $request->isSecure()) {
            return response()->json(['message' => 'HTTPS required'], 403)
                ->header('Cache-Control', 'private, no-store');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
