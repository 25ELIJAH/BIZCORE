<?php

namespace Tests\Feature;

use App\Livewire\Account\Index;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = $this->userWithRole('sales-officer', $this->company);
        $this->user->forceFill(['password' => 'OldPass123'])->save();
        $this->actingAs($this->user);
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_user_can_change_their_own_password(): void
    {
        Livewire::test(Index::class)
            ->set('currentPassword', 'OldPass123')->set('newPassword', 'NewPass456')->set('newPasswordConfirmation', 'NewPass456')
            ->call('changePassword')->assertHasNoErrors()->assertSee('Your password has been changed')
            ->assertSet('currentPassword', '')->assertSet('newPassword', '');

        $this->assertTrue(Hash::check('NewPass456', $this->user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.password_changed', 'user_id' => $this->user->id]);
    }

    public function test_wrong_current_password_weak_password_and_mismatch_are_refused(): void
    {
        Livewire::test(Index::class)
            ->set('currentPassword', 'nope')->set('newPassword', 'NewPass456')->set('newPasswordConfirmation', 'NewPass456')
            ->call('changePassword')->assertHasErrors('currentPassword');

        Livewire::test(Index::class)
            ->set('currentPassword', 'OldPass123')->set('newPassword', 'short1')->set('newPasswordConfirmation', 'short1')
            ->call('changePassword')->assertHasErrors('newPassword');

        Livewire::test(Index::class)
            ->set('currentPassword', 'OldPass123')->set('newPassword', 'NewPass456')->set('newPasswordConfirmation', 'Different789')
            ->call('changePassword')->assertHasErrors('newPassword');

        $this->assertTrue(Hash::check('OldPass123', $this->user->fresh()->password));
    }

    public function test_account_page_is_reachable_from_the_menu_avatar(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('account'), false);
        $this->get(route('account'))->assertOk()->assertSee('Change password');
    }

    public function test_forgot_password_sends_a_reset_link_and_the_login_page_links_to_it(): void
    {
        auth()->logout();
        Notification::fake();

        $this->get(route('login'))->assertSee(route('password.request'), false);
        $this->post(route('password.email'), ['email' => $this->user->email]);

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function test_a_mail_failure_does_not_crash_forgot_password_and_is_logged(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')->andThrow(new \RuntimeException('smtp refused'));
        auth()->logout();

        $this->post(route('password.email'), ['email' => $this->user->email])->assertSessionHas('status');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->withArgs(fn ($m) => str_contains($m, 'smtp refused'))->once();
    }
}
