<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo companies and users for local development ONLY. Refuses to run elsewhere.
 * Demo accounts use DEMO_PASSWORD (default "password", local only).
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') || config('bizcore.allow_demo_seed'))) {
            $this->command?->warn('DevelopmentSeeder skipped: only runs in the local environment (or with ALLOW_DEMO_SEED=true).');

            return;
        }

        $password = config('bizcore.demo_password');

        $acme = Company::firstOrCreate(['name' => 'Acme Trading Ltd'], ['currency' => 'KES', 'email' => 'info@acme.test']);
        $globex = Company::firstOrCreate(['name' => 'Globex Supplies'], ['currency' => 'KES', 'email' => 'info@globex.test']);

        $roles = Role::pluck('id', 'slug');

        User::updateOrCreate(['email' => 'super@bizcore.test'], [
            'name' => 'Super Admin', 'password' => $password, 'is_super_admin' => true,
        ]);

        $admin = User::updateOrCreate(['email' => 'admin@bizcore.test'], ['name' => 'Company Admin', 'password' => $password]);
        $admin->companies()->syncWithoutDetaching([$acme->id => ['role_id' => $roles['company-administrator']]]);

        // One person, different roles in different companies.
        $multi = User::updateOrCreate(['email' => 'multi@bizcore.test'], ['name' => 'Multi Company User', 'password' => $password]);
        $multi->companies()->syncWithoutDetaching([
            $acme->id => ['role_id' => $roles['manager']],
            $globex->id => ['role_id' => $roles['accountant']],
        ]);
    }
}
