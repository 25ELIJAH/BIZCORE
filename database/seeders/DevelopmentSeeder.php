<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo companies and users for local development ONLY. Refuses to run elsewhere.
 * Demo accounts all use the password "password".
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DevelopmentSeeder skipped: only runs in the local environment.');

            return;
        }

        $acme = Company::firstOrCreate(['name' => 'Acme Trading Ltd'], ['currency' => 'USD', 'email' => 'info@acme.test']);
        $globex = Company::firstOrCreate(['name' => 'Globex Supplies'], ['currency' => 'USD', 'email' => 'info@globex.test']);

        $roles = Role::pluck('id', 'slug');

        User::updateOrCreate(['email' => 'super@bizcore.test'], [
            'name' => 'Super Admin', 'password' => 'password', 'is_super_admin' => true,
        ]);

        $admin = User::updateOrCreate(['email' => 'admin@bizcore.test'], ['name' => 'Company Admin', 'password' => 'password']);
        $admin->companies()->syncWithoutDetaching([$acme->id => ['role_id' => $roles['company-administrator']]]);

        // One person, different roles in different companies.
        $multi = User::updateOrCreate(['email' => 'multi@bizcore.test'], ['name' => 'Multi Company User', 'password' => 'password']);
        $multi->companies()->syncWithoutDetaching([
            $acme->id => ['role_id' => $roles['manager']],
            $globex->id => ['role_id' => $roles['accountant']],
        ]);
    }
}
