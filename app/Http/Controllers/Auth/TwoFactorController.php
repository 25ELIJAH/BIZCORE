<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    /** Show the QR code so the user can link their authenticator app. */
    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('home');
        }

        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->twoFactor->newSecret()])->save();
        }

        return view('auth.two-factor-setup', [
            'secret' => $user->two_factor_secret,
            'qr' => $this->twoFactor->qrSvg($this->twoFactor->url($user, $user->two_factor_secret)),
        ]);
    }

    /** Confirm the first code, switch two-factor on and show the recovery codes once. */
    public function confirm(Request $request, ActivityLogger $log): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if ($user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return redirect()->route('two-factor.setup');
        }

        if (! $this->twoFactor->verify($user->two_factor_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Check the 6 digits in your app and try again.']);
        }

        [$plain, $hashed] = $this->twoFactor->newRecoveryCodes();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $hashed])->save();
        $request->session()->put(TwoFactor::SESSION_KEY, true);
        $request->session()->flash('recovery_codes', $plain);
        $log->log('account.two_factor_enabled', $user);

        return redirect()->route('two-factor.recovery');
    }

    /** One-time display of the recovery codes (they are stored hashed and cannot be shown again). */
    public function recovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! $codes) {
            return redirect()->route('home');
        }

        $request->session()->keep('recovery_codes');

        return view('auth.two-factor-recovery', ['codes' => $codes]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }

        return $request->session()->get(TwoFactor::SESSION_KEY) ? redirect()->route('home') : view('auth.two-factor-challenge');
    }

    public function verify(Request $request, ActivityLogger $log): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $key = 'two-factor|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $input = $request->string('code')->toString();
        $ok = $this->twoFactor->verify((string) $user->two_factor_secret, $input);
        $usedRecovery = false;

        if (! $ok && str_contains($input, '-')) {
            $ok = $usedRecovery = $this->twoFactor->useRecoveryCode($user, $input);
        }

        if (! $ok) {
            RateLimiter::hit($key);
            $log->log('auth.two_factor_failed', $user);

            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        RateLimiter::clear($key);
        $request->session()->put(TwoFactor::SESSION_KEY, true);
        $log->log($usedRecovery ? 'auth.two_factor_recovery_used' : 'auth.two_factor_passed', $user);

        return redirect()->intended(route('home'));
    }
}
