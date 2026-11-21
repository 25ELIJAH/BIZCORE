<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_user_can_log_in_and_login_is_audited(): void
    {
        $user = $this->userWithRole('manager', attributes: ['email' => 'jane@example.test', 'password' => 'Secret123']);

        $this->post('/login', ['email' => 'jane@example.test', 'password' => 'Secret123'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_passwords_are_hashed_in_the_database(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);

        $this->assertNotSame('Secret123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('Secret123', $user->getRawOriginal('password')));
    }

    public function test_wrong_password_is_rejected_and_the_attempt_is_logged_without_the_password(): void
    {
        User::factory()->create(['email' => 'jane@example.test', 'password' => 'Secret123']);

        $this->post('/login', ['email' => 'jane@example.test', 'password' => 'Wrong-pass1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $log = ActivityLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertStringNotContainsString('Wrong-pass1', json_encode($log->properties));
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@example.test', 'password' => 'Secret123', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@example.test', 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_repeated_failures_even_with_the_right_password(): void
    {
        User::factory()->create(['email' => 'jane@example.test', 'password' => 'Secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'jane@example.test', 'password' => 'bad']);
        }

        $this->post('/login', ['email' => 'jane@example.test', 'password' => 'Secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.test', 'password' => 'OldSecret1']);

        $this->post('/forgot-password', ['email' => 'jane@example.test'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'jane@example.test',
            'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }

    public function test_reset_request_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_reset_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->create(['password' => 'OldSecret1']);

        $this->post('/reset-password', [
            'token' => 'not-a-real-token', 'email' => $user->email,
            'password' => 'NewSecret123', 'password_confirmation' => 'NewSecret123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldSecret1', $user->fresh()->password));
    }
}
