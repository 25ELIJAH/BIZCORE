<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Access\DefaultRoles;
use App\Support\Access\PermissionCatalog;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_all_nine_default_roles_are_seeded_with_their_permissions(): void
    {
        $this->seedAccess();

        $this->assertSame(count(DefaultRoles::definitions()), Role::count());
        $this->assertSame(count(PermissionCatalog::slugs()), Permission::count());
        $this->assertTrue(Role::where('slug', 'sales-officer')->first()->permissions()->where('slug', 'sales.create')->exists());
        $this->assertFalse(Role::where('slug', 'sales-officer')->first()->permissions()->where('slug', 'finance.manage')->exists());
    }

    public function test_seeding_twice_keeps_administrator_edits_to_roles(): void
    {
        $this->seedAccess();
        $role = Role::where('slug', 'employee')->first();
        $role->permissions()->detach(Permission::where('slug', 'leave.request')->value('id'));

        $this->seedAccess();

        $this->assertFalse($role->permissions()->where('slug', 'leave.request')->exists());
    }

    public function test_permissions_follow_the_role_in_the_current_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $user = $this->userWithRole('sales-officer', $a);
        $user->companies()->attach($b->id, ['role_id' => Role::where('slug', 'accountant')->value('id')]);

        $this->assertTrue($user->hasPermissionIn($a, 'sales.create'));
        $this->assertFalse($user->hasPermissionIn($a, 'finance.manage'));
        // Different role, different permissions, in the other company.
        $this->assertFalse($user->hasPermissionIn($b, 'sales.create'));
        $this->assertTrue($user->hasPermissionIn($b, 'finance.manage'));
        // No assignment at all => nothing.
        $this->assertFalse($user->hasPermissionIn(Company::factory()->create(), 'sales.view'));
    }

    public function test_gates_evaluate_against_the_current_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $user = $this->userWithRole('sales-officer', $a);
        $user->companies()->attach($b->id, ['role_id' => Role::where('slug', 'employee')->value('id')]);
        $this->actingAs($user);

        app(CurrentCompany::class)->set($a);
        $this->assertTrue(Gate::allows('sales.create'));

        app(CurrentCompany::class)->set($b);
        $this->assertFalse(Gate::allows('sales.create'));
    }

    public function test_routes_are_protected_on_the_backend_not_just_hidden_in_the_menu(): void
    {
        Route::middleware(['web', 'auth', 'company', 'can:sales.cancel'])->post('/probe/cancel-sale', fn () => 'cancelled');

        $officer = $this->userWithRole('sales-officer');
        $manager = $this->userWithRole('manager');

        $this->actingAs($officer)->post('/probe/cancel-sale')->assertForbidden();
        $this->actingAs($manager)->post('/probe/cancel-sale')->assertOk();
    }

    public function test_super_admin_passes_every_permission_check(): void
    {
        $company = Company::factory()->create();
        $super = User::factory()->create(['is_super_admin' => true]);

        foreach (PermissionCatalog::slugs() as $slug) {
            $this->assertTrue($super->hasPermissionIn($company, $slug), $slug);
        }
    }

    public function test_deactivated_users_have_no_permissions(): void
    {
        $company = Company::factory()->create();
        $user = $this->userWithRole('company-administrator', $company, ['is_active' => false]);

        $this->assertFalse($user->hasPermissionIn($company, 'sales.view'));
    }

    public function test_only_super_admins_can_open_the_filament_admin_panel(): void
    {
        $this->actingAs($this->userWithRole('company-administrator'))->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))->get('/admin')->assertOk();
    }

    public function test_activity_log_redacts_sensitive_values_at_any_depth(): void
    {
        $company = Company::factory()->create();
        $user = $this->userWithRole('manager', $company);
        $this->actingAs($user);
        app(CurrentCompany::class)->set($company);

        $entry = app(ActivityLogger::class)->log('user.updated', $user, [
            'old' => ['name' => 'A', 'password' => 'hash-old'],
            'new' => ['name' => 'B', 'password' => 'hash-new', 'nested' => ['token' => 'abc']],
        ]);

        $json = json_encode($entry->fresh()->properties);
        $this->assertStringNotContainsString('hash-old', $json);
        $this->assertStringNotContainsString('hash-new', $json);
        $this->assertStringNotContainsString('abc', $json);
        $this->assertSame('B', $entry->fresh()->properties['new']['name']);
        $this->assertSame($company->id, $entry->company_id);
    }
}
