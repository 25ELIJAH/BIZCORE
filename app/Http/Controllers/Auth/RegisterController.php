<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Self-service sign-up: a new business creates its own company and becomes its administrator. */
class RegisterController extends Controller
{
    public function create(): View
    {
        abort_unless(config('bizcore.self_signup'), 404);

        return view('auth.register');
    }

    public function store(Request $request, ActivityLogger $log): RedirectResponse
    {
        abort_unless(config('bizcore.self_signup'), 404);

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'An account with this email already exists. Sign in instead, or use a different email.',
        ]);

        // Bots fill every field; people never see this one. Pretend it worked.
        if ($request->filled('contact_url')) {
            return redirect()->route('login');
        }

        $roleId = Role::where('slug', 'company-administrator')->value('id');
        abort_if($roleId === null, 503, 'The system is not ready for sign-ups yet.');

        [$company, $user] = DB::transaction(function () use ($data, $roleId) {
            $company = Company::create([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true])->save();
            $user->companies()->attach($company->id, ['role_id' => $roleId]);

            return [$company, $user];
        });

        $log->log('company.registered', $company, ['new' => ['name' => $company->name, 'admin' => $user->email]], companyId: $company->id, userId: $user->id);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(\App\Support\TwoFactor::SESSION_KEY);
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('company.settings')->with('welcome', true);
    }
}
