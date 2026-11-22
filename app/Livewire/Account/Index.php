<?php

namespace App\Livewire\Account;

use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "My account": every signed-in user can change their own password. */
#[Layout('layouts.app')]
#[Title('My account')]
class Index extends Component
{
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $notice = '';

    public function changePassword(ActivityLogger $log): void
    {
        $this->notice = '';
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'same:newPasswordConfirmation', Password::min(8)->letters()->numbers()],
            'newPasswordConfirmation' => ['required', 'string'],
        ], [
            'newPassword.same' => 'The new password and its confirmation do not match.',
        ], [
            'currentPassword' => 'current password',
            'newPassword' => 'new password',
            'newPasswordConfirmation' => 'confirmation',
        ]);

        $user = Auth::user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'That is not your current password.');

            return;
        }

        if (Hash::check($this->newPassword, $user->password)) {
            $this->addError('newPassword', 'Choose a password different from your current one.');

            return;
        }

        $user->forceFill(['password' => $this->newPassword])->save(); // hashed by the model cast
        $log->log('account.password_changed', $user);

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->notice = 'Your password has been changed.';
    }

    public function render()
    {
        return view('livewire.account.index', ['user' => Auth::user()]);
    }
}
