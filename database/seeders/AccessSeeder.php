<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access\DefaultRoles;
use App\Support\Access\PermissionCatalog;
use Illuminate\Database\Seeder;

/** Seeds permissions and the default roles. Safe for production and idempotent. */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::groups() as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }

        $permissionIds = Permission::pluck('id', 'slug');

        foreach (DefaultRoles::definitions() as $slug => $definition) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name'], 'description' => $definition['description'], 'is_system' => true],
            );

            // Only set permissions when the role is first created, so administrator edits survive re-seeding.
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(
                    collect($definition['permissions'])->map(fn (string $slug) => $permissionIds[$slug])->all()
                );
            }
        }
    }
}
