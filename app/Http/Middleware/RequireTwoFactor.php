<?php

namespace App\Http\Middleware;

use App\Support\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Signed-in users must have an authenticator app set up and must have entered a code in this session. */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! TwoFactor::required()) {
            return $next($request);
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->get(TwoFactor::SESSION_KEY)) {
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
