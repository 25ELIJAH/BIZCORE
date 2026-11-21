<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CompanySwitchingTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_user_can_switch_between_their_companies(): void
    {
        $a = Company::factory()->create(['name' => 'Alpha']);
        $b = Company::factory()->create(['name' => 'Beta']);
        $user = $this->userWithRole('manager', $a);
        $user->companies()->attach($b->id, ['role_id' => Role::where('slug', 'accountant')->value('id')]);

        $this->actingAs($user)->get('/')->assertSee('Alpha');

        $this->actingAs($user)->post('/company/switch', ['company_id' => $b->id])->assertRedirect('/');
        $this->get('/')->assertSee('Beta')->assertSessionHas('current_company_id', $b->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'company.switched', 'company_id' => $b->id, 'user_id' => $user->id]);
    }

    public function test_user_cannot_switch_to_a_company_they_are_not_assigned_to(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->userWithRole('manager', $mine);

        $this->actingAs($user)->post('/company/switch', ['company_id' => $other->id])->assertForbidden();

        $this->get('/')->assertSessionHas('current_company_id', $mine->id);
    }

    public function test_a_forged_session_company_id_is_ignored(): void
    {
        $mine = Company::factory()->create();
        $other = Company::factory()->create(['name' => 'Secret Corp']);
        $user = $this->userWithRole('manager', $mine);

        $this->actingAs($user)->withSession(['current_company_id' => $other->id])
            ->get('/')->assertOk()->assertDontSee('Secret Corp')->assertSessionHas('current_company_id', $mine->id);
    }

    public function test_deactivated_company_is_not_accessible(): void
    {
        $active = Company::factory()->create();
        $inactive = Company::factory()->inactive()->create();
        $user = $this->userWithRole('manager', $active);
        $user->companies()->attach($inactive->id, ['role_id' => Role::where('slug', 'manager')->value('id')]);

        $this->actingAs($user)->post('/company/switch', ['company_id' => $inactive->id])->assertForbidden();
    }

    public function test_user_with_no_company_is_sent_to_the_no_company_page(): void
    {
        $this->seedAccess();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect('/no-company');
        $this->get('/no-company')->assertOk();
    }

    public function test_super_admin_can_enter_any_active_company(): void
    {
        $company = Company::factory()->create();
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->post('/company/switch', ['company_id' => $company->id])->assertRedirect('/');
        $this->get('/')->assertSessionHas('current_company_id', $company->id);
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->userWithRole('manager');
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
