<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    public function test_super_admin_can_create_a_company_and_it_is_audited(): void
    {
        Livewire::test(CreateCompany::class)
            ->fillForm(['name' => 'Nile Traders', 'registration_number' => 'REG-1', 'currency' => 'KES', 'is_active' => true, 'email' => 'hello@nile.test'])
            ->call('create')->assertHasNoFormErrors();

        $company = Company::where('name', 'Nile Traders')->firstOrFail();
        $this->assertSame('KES', $company->currency);
        $this->assertTrue($company->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.created', 'company_id' => $company->id]);
    }

    public function test_company_registration_number_must_be_unique_and_name_is_required(): void
    {
        Company::factory()->create(['registration_number' => 'REG-DUP']);

        Livewire::test(CreateCompany::class)
            ->fillForm(['name' => 'Other', 'registration_number' => 'REG-DUP', 'currency' => 'USD'])
            ->call('create')->assertHasFormErrors(['registration_number']);
        Livewire::test(CreateCompany::class)
            ->fillForm(['name' => '', 'currency' => 'USD'])->call('create')->assertHasFormErrors(['name' => 'required']);
    }

    public function test_company_can_be_edited_and_the_negative_stock_setting_saved(): void
    {
        $company = Company::factory()->create(['name' => 'Old Name']);

        Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
            ->fillForm(['name' => 'New Name', 'settings' => ['allow_negative_stock' => true]])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('New Name', $company->fresh()->name);
        $this->assertTrue($company->fresh()->setting('allow_negative_stock'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.updated', 'company_id' => $company->id]);
    }

    public function test_company_can_be_deactivated_and_reactivated_and_users_lose_access_meanwhile(): void
    {
        $company = Company::factory()->create();
        $member = User::factory()->create();
        $member->companies()->attach($company->id, ['role_id' => Role::where('slug', 'manager')->value('id')]);

        Livewire::test(ListCompanies::class)->callAction(TestAction::make('toggleActive')->table($company));
        $this->assertFalse($company->fresh()->is_active);
        $this->assertFalse($member->canAccessCompany($company));
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.deactivated', 'company_id' => $company->id]);

        Livewire::test(ListCompanies::class)->callAction(TestAction::make('toggleActive')->table($company));
        $this->assertTrue($company->fresh()->is_active);
        $this->assertTrue($member->canAccessCompany($company));
    }

    public function test_a_user_can_be_assigned_to_companies_with_different_roles(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $user = User::factory()->create();
        $manager = Role::where('slug', 'manager')->value('id');
        $accountant = Role::where('slug', 'accountant')->value('id');

        foreach ([[$a, $manager], [$b, $accountant]] as [$company, $roleId]) {
            Livewire::test(UsersRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditCompany::class])
                ->callAction(TestAction::make('attach')->table(), ['recordId' => $user->id, 'role_id' => $roleId])
                ->assertHasNoFormErrors();
        }

        $this->assertSame('Manager', $user->roleIn($a)->name);
        $this->assertSame('Accountant', $user->roleIn($b)->name);
        $this->assertTrue($user->hasPermissionIn($a, 'sales.cancel'));
        $this->assertFalse($user->hasPermissionIn($b, 'sales.cancel'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.user_assigned', 'company_id' => $b->id]);
    }

    public function test_user_creation_hashes_the_password_and_sets_flags_explicitly(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'New Person', 'email' => 'new@example.test', 'password' => 'Str0ngPass', 'is_active' => true, 'is_super_admin' => false])
            ->call('create')->assertHasNoFormErrors();

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Str0ngPass', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->is_super_admin);
        $log = ActivityLog::where('action', 'user.created')->firstOrFail();
        $this->assertStringNotContainsString('Str0ngPass', json_encode($log->properties));
        $this->assertStringNotContainsString($user->password, json_encode($log->properties));
    }

    public function test_weak_passwords_are_rejected(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'x@example.test', 'password' => 'short'])
            ->call('create')->assertHasFormErrors(['password']);
    }

    public function test_editing_a_user_without_a_password_keeps_the_old_password_and_never_logs_hashes(): void
    {
        $user = User::factory()->create(['password' => 'OldPass123']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Renamed', 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('OldPass123', $user->password));
        $log = ActivityLog::where('action', 'user.updated')->firstOrFail();
        $this->assertStringNotContainsString($user->password, json_encode($log->properties));
    }

    public function test_company_admins_cannot_reach_the_administration_resources(): void
    {
        $company = Company::factory()->create();
        $admin = $this->userWithRole('company-administrator', $company);

        $this->actingAs($admin)->get('/admin/companies')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }
}
