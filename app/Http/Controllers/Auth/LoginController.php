<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $logger->log('auth.lockout', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        // Only active users may sign in; deactivated accounts get the same generic error.
        $attempt = Auth::attempt(
            [...$credentials, 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $attempt) {
            RateLimiter::hit($throttleKey);
            $logger->log('auth.login_failed', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->forget(\App\Support\TwoFactor::SESSION_KEY);

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();
        $logger->log('auth.login', $user);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $logger->log('auth.logout', $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
