<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicReferenceCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // These endpoints have no session/customer pricing. Query variants have
        // distinct URLs; errors and responses setting a cookie must stay uncached.
        if ($request->isMethod('GET') && $request->is('api/v1/categories/tree', 'api/v1/rooms/tree', 'api/v1/address-directory/regions')
            && $response->getStatusCode() === 200 && $response->headers->getCookies() === []
            && ! $response->headers->hasCacheControlDirective('no-store')) {
            $response->setPublic();
            $response->setMaxAge((int) config('api_metrics.reference_max_age', 300));
            $response->setEtag(hash('sha256', $response->getContent()), true);
            $response->isNotModified($request);
        }
        return $response;
    }
}
