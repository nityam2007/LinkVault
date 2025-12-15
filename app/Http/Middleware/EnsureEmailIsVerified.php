<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure email is verified middleware.
 */
class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $redirectToRoute = null): Response
    {
        if (!$request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
            !$request->user()->hasVerifiedEmail())) {
            return $request->expectsJson()
                    ? response()->json(['message' => 'Your email address is not verified.'], 403)
                    : redirect()->route($redirectToRoute ?: 'verification.notice');
        }

        return $next($request);
    }
}
