<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'registration_number', 'address', 'email', 'phone', 'logo_path', 'currency', 'is_active', 'settings'])]
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'settings' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role_id')->withTimestamps();
    }

    /** @return array<string, string> slug => label */
    public function paymentMethods(): array
    {
        $configured = $this->setting('payment_methods');

        return is_array($configured) && $configured !== [] ? $configured : config('bizcore.payment_methods');
    }

    public function paymentTermsDays(): int
    {
        return (int) $this->setting('payment_terms_days', config('bizcore.default_payment_terms_days'));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
