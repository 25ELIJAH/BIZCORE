<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bizcore.two_factor_required' => true]);
        TwoFactor::forgetSchemaCache();
        $company = Company::factory()->create();
        $this->user = $this->userWithRole('sales-officer', $company);
        $this->actingAs($this->user);
        app(CurrentCompany::class)->set($company);
    }

    private function code(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    public function test_user_without_setup_is_sent_to_setup_and_can_enable_it(): void
    {
        $this->get(route('home'))->assertRedirect(route('two-factor.setup'));
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('<svg', false);

        $secret = $this->user->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.confirm'), ['code' => $this->code($secret)])->assertRedirect(route('two-factor.recovery'));

        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
        $this->get(route('two-factor.recovery'))->assertOk()->assertSee('Save these recovery codes');
        $this->get(route('home'))->assertOk();
    }

    public function test_enabled_user_must_pass_the_challenge_each_session(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $this->user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->get(route('home'))->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get(route('home'))->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), ['code' => $this->code($secret)])->assertRedirect();
        $this->get(route('home'))->assertOk();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $tf = app(TwoFactor::class);
        [$plain, $hashed] = $tf->newRecoveryCodes();
        $this->user->forceFill(['two_factor_secret' => $tf->newSecret(), 'two_factor_recovery_codes' => $hashed, 'two_factor_confirmed_at' => now()])->save();

        $this->post(route('two-factor.verify'), ['code' => $plain[0]])->assertRedirect();
        $this->get(route('home'))->assertOk();
        $this->assertCount(9, $this->user->fresh()->two_factor_recovery_codes);

        $this->flushSession();
        $this->post(route('two-factor.verify'), ['code' => $plain[0]])->assertSessionHasErrors('code');
    }

    public function test_attempts_are_throttled(): void
    {
        $this->user->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(), 'two_factor_confirmed_at' => now()])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.verify'), ['code' => '111111']);
        }

        $this->post(route('two-factor.verify'), ['code' => '111111'])->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many', session('errors')->first('code'));
    }

    public function test_every_sign_in_asks_for_a_code_even_with_remember_me(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $this->user->forceFill(['password' => 'Secret123', 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        auth()->logout();

        for ($i = 0; $i < 2; $i++) {
            $this->flushSession();
            $this->post(route('login'), ['email' => $this->user->email, 'password' => 'Secret123', 'remember' => '1'])->assertRedirect();
            $this->get(route('home'))->assertRedirect(route('two-factor.challenge'));
            $this->post(route('two-factor.verify'), ['code' => $this->code($secret)])->assertRedirect();
            $this->get(route('home'))->assertOk();
            $this->post(route('logout'));
        }
    }
}
