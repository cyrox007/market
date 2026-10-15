<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PricingCustomerContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Freeze the authenticated buyer BEFORE checkout auto-registration/login.
        $request->attributes->set('pricing_customer', auth('sanctum')->user() ?? $request->user());
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setVary(['Cookie', 'Authorization'], false);

        return $response;
    }
}
