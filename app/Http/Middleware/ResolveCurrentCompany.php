<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context for every authenticated request.
 *
 * The session only stores a company id. It is re-validated against the user's
 * assignments on every request, so a forged or stale value never grants access.
 */
class ResolveCurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public function __construct(private CurrentCompany $currentCompany) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated.']);
        }

        $accessible = $user->accessibleCompanies();
        $company = (clone $accessible)->find($request->session()->get(self::SESSION_KEY))
            ?? (clone $accessible)->first();

        if ($company === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('company.none');
        }

        $request->session()->put(self::SESSION_KEY, $company->getKey());
        $this->currentCompany->set($company);

        return $next($request);
    }
}
