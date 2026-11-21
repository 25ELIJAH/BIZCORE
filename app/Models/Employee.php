<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Written only through App\Services\EmployeeService. national_id / salary / bank_account are sensitive. */
class Employee extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const ACTIVE = 'active';

    public const TERMINATED = 'terminated';

    protected $guarded = ['*'];

    /** Never serialised, so they cannot leak through toArray()/JSON by accident. */
    protected $hidden = ['national_id', 'salary', 'bank_account'];

    protected function casts(): array
    {
        return ['hire_date' => 'date', 'termination_date' => 'date', 'salary' => 'decimal:2'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
