<?php

namespace App\Http\Middleware;

use App\Services\Audit\ActivityAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditUserActivity
{
    // Match route templates, never raw URLs or client-provided event names.
    private const EVENTS = [
        'POST api/v1/auth/login' => ['security','auth.login'],
        'POST api/v1/auth/logout' => ['security','auth.logout'],
        'POST api/v1/auth/register' => ['account','auth.register'],
        'POST api/v1/auth/password/forgot' => ['security','auth.password_requested'],
        'POST api/v1/auth/password/reset' => ['security','auth.password_reset'],
        'PUT api/v1/auth/password' => ['security','auth.password_changed'],
        'PUT api/v1/auth/profile' => ['account','profile.updated'],
        'POST api/v1/auth/email/change' => ['security','email.change_requested'],
        'POST api/v1/auth/email/confirm' => ['security','email.confirmed'],
        'POST api/v1/addresses' => ['account','address.created'],
        'PUT api/v1/addresses/{address}' => ['account','address.updated','address'],
        'DELETE api/v1/addresses/{address}' => ['account','address.deleted','address'],
        'POST api/v1/cart' => ['shopping','cart.add'],
        'PUT api/v1/cart/{itemId}' => ['shopping','cart.update','cart_item'],
        'DELETE api/v1/cart/{itemId}' => ['shopping','cart.remove','cart_item'],
        'DELETE api/v1/cart' => ['shopping','cart.clear'],
        'POST api/v1/wishlist/{productId}' => ['shopping','wishlist.add','product'],
        'DELETE api/v1/wishlist/{productId}' => ['shopping','wishlist.remove','product'],
        'POST api/v1/wishlist/{productId}/toggle' => ['shopping','wishlist.toggle','product'],
        'POST api/v1/compare/{productId}' => ['shopping','compare.add','product'],
        'DELETE api/v1/compare/{productId}' => ['shopping','compare.remove','product'],
        'DELETE api/v1/compare' => ['shopping','compare.clear'],
        'POST api/v1/shipping/quote' => ['shopping','checkout.quote'],
        'POST api/v1/shipping/pickup-options' => ['shopping','checkout.pickup_options'],
        'POST api/v1/orders' => ['order','checkout.submit'],
        'POST api/v1/orders/one-click' => ['order','checkout.one_click'],
        'GET api/v1/orders/{id}' => ['order','order.view','order'],
        'GET api/v1/order-access/{id}' => ['order','order.view','order'],
        'GET api/v1/orders/{id}/payment-config' => ['order','payment.request','order'],
        'POST api/v1/orders/{id}/cancel' => ['order','order.cancel','order'],
        'POST api/v1/order-access/{id}/cancel' => ['order','order.cancel','order'],
        'POST api/v1/order-access/{id}/cancellation-request' => ['order','order.cancellation_request','order'],
        'GET api/v1/products/{slug}' => ['behavior','catalog.product_view'],
        'GET api/v1/products/search' => ['behavior','catalog.search'],
        'GET api/v1/products' => ['behavior','catalog.list'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Session middleware executes inside this middleware. Capture pre-logout
        // identity using the dedicated auth event below, not raw cookie data.
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $audit = app(ActivityAudit::class);
        if (! $audit->enabled() || ! $request->route()) { return; }
        $spec = self::EVENTS[$request->method().' '.$request->route()->uri()] ?? null;
        if (! $spec || ($spec[0] === 'behavior' && ! config('activity_audit.behavior'))) { return; }
        $status = $response->getStatusCode();
        $id = null;
        foreach (['id','itemId','productId','address'] as $parameter) {
            $value = $request->route($parameter);
            if (is_scalar($value) && ctype_digit((string) $value)) { $id = (int) $value; break; }
        }
        $result = $status >= 500 ? 'error' : ($status >= 400 ? 'denied' : 'success');
        $details = ['http_status' => $status];
        if ($spec[1] === 'cart.add' && $result === 'success') {
            $details += ['product_id' => $request->input('product_id'), 'quantity' => $request->input('quantity')];
        }
        $audit->bestEffort($spec[0], $spec[1], $spec[2] ?? null, $id, $result, $details);
    }
}
