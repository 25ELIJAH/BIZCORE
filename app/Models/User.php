<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<int, list<string>> per-instance cache: company id => permission slugs */
    private array $permissionCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role_id')->withTimestamps();
    }

    /** Active companies this user may operate in. Super administrators may enter any active company. */
    public function accessibleCompanies(): Builder
    {
        $query = Company::query()->where('companies.is_active', true)->orderBy('companies.name');

        return $this->is_super_admin
            ? $query
            : $query->whereIn('companies.id', $this->companies()->select('companies.id'));
    }

    public function canAccessCompany(Company|int $company): bool
    {
        $id = $company instanceof Company ? $company->getKey() : $company;

        return $this->accessibleCompanies()->whereKey($id)->exists();
    }

    public function roleIn(Company|int $company): ?Role
    {
        $id = $company instanceof Company ? $company->getKey() : $company;
        $roleId = $this->companies()->whereKey($id)->first()?->pivot->role_id;

        return $roleId ? Role::find($roleId) : null;
    }

    /** Permission check in a given company. Super admins pass everything; inactive users nothing. */
    public function hasPermissionIn(Company|int|null $company, string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->is_super_admin) {
            return true;
        }

        $companyId = $company instanceof Company ? $company->getKey() : $company;

        if ($companyId === null) {
            return false;
        }

        $this->permissionCache[$companyId] ??= $this->loadPermissions($companyId);

        return in_array($permission, $this->permissionCache[$companyId], true);
    }

    /** @return list<string> */
    private function loadPermissions(int $companyId): array
    {
        $roleId = $this->companies()->whereKey($companyId)->first()?->pivot->role_id;

        if (! $roleId) {
            return [];
        }

        return Permission::query()
            ->whereIn('id', Role::find($roleId)->permissions()->select('permissions.id'))
            ->pluck('slug')
            ->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->is_super_admin;
    }
}
