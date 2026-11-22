<?php

namespace Tests\Feature;

use App\Livewire\Company\Settings;
use App\Livewire\Company\Team;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SelfSignupTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function payload(array $over = []): array
    {
        return array_merge([
            'company_name' => 'Acme Traders', 'name' => 'Amina Otieno', 'email' => 'amina@acme.test', 'phone' => '0700000000',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ], $over);
    }

    public function test_a_new_business_can_register_and_becomes_its_administrator(): void
    {
        $this->seedAccess();
        $this->get(route('login'))->assertSee(route('register'), false);
        $this->get(route('register'))->assertOk()->assertSee('Create your company');

        $this->post(route('register'), $this->payload())->assertRedirect(route('company.settings'));

        $company = Company::where('name', 'Acme Traders')->firstOrFail();
        $user = User::where('email', 'amina@acme.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($company->is_active);
        $this->assertSame('company-administrator', $user->roleIn($company)->slug);
        $this->assertFalse($user->is_super_admin);
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.registered', 'company_id' => $company->id]);
        $this->get(route('company.settings'))->assertOk()->assertSee('Acme Traders');
    }

    public function test_duplicate_email_weak_password_and_bots_are_refused(): void
    {
        $this->seedAccess();
        User::factory()->create(['email' => 'amina@acme.test']);

        $this->post(route('register'), $this->payload())->assertSessionHasErrors('email');
        $this->post(route('register'), $this->payload(['email' => 'new@acme.test', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post(route('register'), $this->payload(['email' => 'bot@acme.test', 'contact_url' => 'http://spam']))->assertRedirect(route('login'));

        $this->assertDatabaseMissing('companies', ['name' => 'Acme Traders']);
        $this->assertDatabaseMissing('users', ['email' => 'bot@acme.test']);
    }

    public function test_sign_up_can_be_switched_off(): void
    {
        config(['bizcore.self_signup' => false]);
        $this->seedAccess();

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), $this->payload())->assertNotFound();
        $this->get(route('login'))->assertDontSee(route('register'), false);
    }

    public function test_a_new_company_cannot_enter_another_company(): void
    {
        $this->seedAccess();
        $other = Company::factory()->create(['name' => 'Other Co']);
        $this->post(route('register'), $this->payload());

        $mine = Company::where('name', 'Acme Traders')->firstOrFail();
        $user = User::where('email', 'amina@acme.test')->firstOrFail();
        $this->assertFalse($user->canAccessCompany($other));
        $this->assertTrue($user->canAccessCompany($mine));
    }

    public function test_administrator_saves_company_and_invoice_details(): void
    {
        $company = Company::factory()->create();
        $admin = $this->userWithRole('company-administrator', $company);
        $this->actingAs($admin);
        app(CurrentCompany::class)->set($company);

        Livewire::test(Settings::class)
            ->set('name', 'Renamed Ltd')->set('bank_name', 'Equity')->set('bank_account_number', '0123456')->set('website', 'www.renamed.test')
            ->call('save')->assertHasNoErrors()->assertSee('Company details saved');

        $company->refresh();
        $this->assertSame('Renamed Ltd', $company->name);
        $this->assertSame('Equity', $company->setting('bank_name'));
        $this->assertSame('www.renamed.test', $company->setting('website'));
    }

    public function test_administrator_manages_the_team_and_others_cannot(): void
    {
        $company = Company::factory()->create();
        $admin = $this->userWithRole('company-administrator', $company);
        $this->actingAs($admin);
        app(CurrentCompany::class)->set($company);
        $salesId = Role::where('slug', 'sales-officer')->value('id');

        Livewire::test(Team::class)
            ->set('name', 'Brian')->set('email', 'brian@acme.test')->set('roleId', $salesId)->set('password', 'Starter123')
            ->call('add')->assertHasNoErrors()->assertSee('Brian was added');

        $brian = User::where('email', 'brian@acme.test')->firstOrFail();
        $this->assertSame('sales-officer', $brian->roleIn($company)->slug);
        $this->assertTrue($brian->is_active);

        $accountantId = Role::where('slug', 'accountant')->value('id');
        Livewire::test(Team::class)->call('changeRole', $brian->id, $accountantId);
        $this->assertSame('accountant', $brian->roleIn($company)->slug);

        // The only administrator cannot be demoted or removed.
        Livewire::test(Team::class)->call('changeRole', $admin->id, $salesId)->assertHasErrors('team');
        $this->assertSame('company-administrator', $admin->roleIn($company)->slug);

        Livewire::test(Team::class)->call('remove', $brian->id);
        $this->assertFalse($brian->fresh()->canAccessCompany($company));

        // A non-administrator is refused.
        $brian->companies()->attach($company->id, ['role_id' => $salesId]);
        $this->actingAs($brian->fresh());
        app(CurrentCompany::class)->set($company);
        $this->get(route('company.team'))->assertForbidden();
        $this->get(route('company.settings'))->assertForbidden();
    }

    public function test_super_admin_role_cannot_be_handed_out_and_other_companies_users_are_untouchable(): void
    {
        $company = Company::factory()->create();
        $admin = $this->userWithRole('company-administrator', $company);
        $outsider = $this->userWithRole('sales-officer', Company::factory()->create());
        $this->actingAs($admin);
        app(CurrentCompany::class)->set($company);

        Livewire::test(Team::class)
            ->set('name', 'X')->set('email', 'x@acme.test')->set('roleId', Role::where('slug', 'super-administrator')->value('id'))->set('password', 'Starter123')
            ->call('add')->assertHasErrors('roleId');

        Livewire::test(Team::class)->call('remove', $outsider->id)->assertNotFound();
        $this->assertCount(1, $outsider->fresh()->companies);
    }
}
