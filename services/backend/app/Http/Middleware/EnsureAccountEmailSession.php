<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountEmailSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if ($user && (int) $request->session()->get('auth_email_version', 0) !== (int) $user->auth_email_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return $next($request);
    }
}
