<?php

namespace App\Livewire\Company;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Company administrators add their own staff, change roles and remove access. */
#[Layout('layouts.app')]
#[Title('Team')]
class Team extends Component
{
    public string $name = '';

    public string $email = '';

    public ?int $roleId = null;

    public string $password = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('manage-company');
    }

    public function add(ActivityLogger $log): void
    {
        $this->authorize('manage-company');
        $this->notice = '';

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'roleId' => ['required', 'integer', 'in:'.$this->assignableRoles()->pluck('id')->implode(',')],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Someone with this email already has an account. Use a different email.',
            'roleId.required' => 'Choose a role.',
        ], ['roleId' => 'role']);

        $user = new User;
        $user->forceFill(['name' => $this->name, 'email' => $this->email, 'password' => $this->password, 'is_active' => true])->save();
        $user->companies()->attach($this->company()->id, ['role_id' => $this->roleId]);
        $log->log('team.member_added', $user, ['new' => ['email' => $user->email, 'role_id' => $this->roleId]]);

        $this->notice = $this->name.' was added. Share their email and the password you set; they can change it under My account.';
        $this->reset('name', 'email', 'roleId', 'password');
    }

    public function changeRole(int $userId, int $roleId, ActivityLogger $log): void
    {
        $this->authorize('manage-company');
        $member = $this->member($userId);

        if (! $this->assignableRoles()->contains('id', $roleId) || $this->isProtected($member)) {
            return;
        }

        if ($this->wouldRemoveLastAdmin($member, $roleId)) {
            $this->addError('team', 'A company needs at least one administrator.');

            return;
        }

        $this->company()->users()->updateExistingPivot($member->id, ['role_id' => $roleId]);
        $log->log('team.role_changed', $member, ['new' => ['role_id' => $roleId]]);
    }

    public function remove(int $userId, ActivityLogger $log): void
    {
        $this->authorize('manage-company');
        $member = $this->member($userId);

        if ($this->isProtected($member) || $member->id === auth()->id()) {
            return;
        }

        if ($this->wouldRemoveLastAdmin($member, null)) {
            $this->addError('team', 'A company needs at least one administrator.');

            return;
        }

        $this->company()->users()->detach($member->id);
        $log->log('team.member_removed', $member, ['old' => ['email' => $member->email]]);
        $this->notice = $member->name.' no longer has access to this company.';
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CurrentCompany::class)->id());
    }

    /** Only people already in this company can be changed from here. */
    private function member(int $userId): User
    {
        return $this->company()->users()->whereKey($userId)->firstOrFail();
    }

    /** Platform staff are managed by the platform owner, not by a company. */
    private function isProtected(User $user): bool
    {
        return (bool) $user->is_super_admin;
    }

    private function wouldRemoveLastAdmin(User $member, ?int $newRoleId): bool
    {
        $adminRoleId = Role::where('slug', 'company-administrator')->value('id');

        if ((int) $member->pivot->role_id !== (int) $adminRoleId || $newRoleId === $adminRoleId) {
            return false;
        }

        return $this->company()->users()->wherePivot('role_id', $adminRoleId)->count() <= 1;
    }

    private function assignableRoles()
    {
        return Role::where('slug', '!=', 'super-administrator')->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.company.team', [
            'members' => $this->company()->users()->orderBy('name')->get(),
            'roles' => $this->assignableRoles(),
        ]);
    }
}
