<?php

namespace App\Http\Controllers\Api;

use App\Services\Pricing\PrivateOfferAccess;
use Illuminate\Http\Request;

class PrivateOfferController
{
    public function activate(Request $request, PrivateOfferAccess $access): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|size:64']);
        $link = $access->activate($data['token']);
        return response()->json(['product_id' => $link->product_id, 'offer_id' => $link->id, 'slug' => $link->product->slug,
            'price' => (float) $link->price, 'expires_at' => $link->expires_at->toISOString()])
            ->withCookie(cookie('has_private_offer', '1', 60 * 24 * 7, '/', null, $request->isSecure(), false, false, 'lax'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function redirect(string $token, PrivateOfferAccess $access): \Illuminate\Http\RedirectResponse
    {
        $link = $access->activate($token);
        return redirect('/product/'.rawurlencode($link->product->slug).'?offer='.$link->id)
            ->withCookie(cookie('has_private_offer', '1', 60 * 24 * 7, '/', null, request()->isSecure(), false, false, 'lax'))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function current(PrivateOfferAccess $access): \Illuminate\Http\JsonResponse
    {
        return response()->json(['offers' => $access->grants()->map(fn ($g) => ['product_id' => $g->product_id,
            'price' => (float) $g->price, 'expires_at' => $g->expires_at->toISOString()])->values()])
            ->header('Cache-Control', 'no-store, private');
    }
}
