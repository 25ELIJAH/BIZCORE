<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;

trait CreatesTenants
{
    protected function seedAccess(): void
    {
        $this->seed(AccessSeeder::class);
    }

    /** Create a user assigned to the given company with a default role (by slug). */
    protected function userWithRole(string $roleSlug, ?Company $company = null, array $attributes = []): User
    {
        $this->seedAccessOnce();
        $company ??= Company::factory()->create();
        $user = User::factory()->create($attributes);
        $user->companies()->attach($company->id, ['role_id' => Role::where('slug', $roleSlug)->value('id')]);

        return $user;
    }

    private function seedAccessOnce(): void
    {
        if (Role::query()->doesntExist()) {
            $this->seedAccess();
        }
    }
}
