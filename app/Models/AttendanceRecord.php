<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half day', 'absent' => 'Absent', 'on_leave' => 'On leave'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
