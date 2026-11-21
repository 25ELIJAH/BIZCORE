<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Created and decided only through App\Services\LeaveService. */
class LeaveRequest extends Model
{
    use BelongsToCompany;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const TYPES = ['annual' => 'Annual leave', 'sick' => 'Sick leave', 'unpaid' => 'Unpaid leave', 'other' => 'Other'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'decided_at' => 'datetime', 'days' => 'decimal:1'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
