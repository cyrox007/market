<?php

namespace App\Http\Middleware;

use App\Services\Integration\OneCOrdersAccessTokens;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeOneCOrderStatusWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $deny = fn (string $message, int $status) => response()
            ->json(['message' => $message], $status)->header('Cache-Control', 'private, no-store');

        if (! config('onec_order_status_write.enabled', false)) {
            return $deny('Integration disabled', 403);
        }
        if (app()->isProduction() && ! $request->isSecure()) {
            return $deny('HTTPS required', 403);
        }

        $plaintext = $request->header('X-1C-Orders-Token');
        if (! is_string($plaintext) || strlen($plaintext) < 32 || strlen($plaintext) > 256) {
            return $deny('Unauthorized', 401);
        }

        $token = app(OneCOrdersAccessTokens::class)->authenticate(
            $plaintext, $request->ip(), OneCOrdersAccessTokens::WRITE_STATUS_ABILITY
        );
        if ($token === null) {
            return $deny('Unauthorized', 401);
        }
        $request->attributes->set('onec_orders_access_token_id', (int) $token->id);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
