<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Leave workflow: pending -> approved | rejected, pending/approved -> cancelled (before it starts).
 * Annual leave is limited by the employee's yearly allocation (assumption A6). Days are working days (Mon-Fri).
 */
class LeaveService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    public static function workingDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekday()) {
                $days++;
            }
        }

        return $days;
    }

    /** @return array{allowance: int, used: string, pending: string, remaining: string} */
    public function balance(Employee $employee, int $year, ?int $ignoreRequestId = null): array
    {
        $sum = fn (string $status) => (string) LeaveRequest::where('employee_id', $employee->id)->where('type', 'annual')->where('status', $status)
            ->whereYear('start_date', $year)->when($ignoreRequestId, fn ($q) => $q->whereKeyNot($ignoreRequestId))->sum('days');

        $used = bcadd($sum(LeaveRequest::APPROVED), '0', 1);
        $pending = bcadd($sum(LeaveRequest::PENDING), '0', 1);

        return [
            'allowance' => (int) $employee->annual_leave_days, 'used' => $used, 'pending' => $pending,
            'remaining' => bcsub(bcsub((string) $employee->annual_leave_days, $used, 1), $pending, 1),
        ];
    }

    public function request(Employee $employee, string $type, string|Carbon $start, string|Carbon $end, ?string $reason = null): LeaveRequest
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if (! array_key_exists($type, LeaveRequest::TYPES)) {
            throw new BusinessRuleException('Unknown leave type.');
        }
        if (! $employee->isActive()) {
            throw new BusinessRuleException('Only active employees can request leave.');
        }
        if ($end->lt($start)) {
            throw new BusinessRuleException('The end date cannot be before the start date.');
        }
        if ($start->year !== $end->year) {
            throw new BusinessRuleException('Leave cannot span two calendar years. Submit one request per year.');
        }
        if ($start->lt(today()->subDays(30))) {
            throw new BusinessRuleException('Leave cannot be requested for dates more than 30 days in the past.');
        }
        $days = self::workingDays($start, $end);
        if ($days === 0) {
            throw new BusinessRuleException('The selected dates contain no working days.');
        }

        return DB::transaction(function () use ($employee, $type, $start, $end, $reason, $days) {
            // Serialise requests per employee so two simultaneous requests cannot both pass the checks below.
            Employee::query()->lockForUpdate()->findOrFail($employee->id);

            $this->assertNoOverlap($employee, $start, $end);
            if ($type === 'annual') {
                $this->assertWithinAllowance($employee, $start->year, (string) $days);
            }

            $request = (new LeaveRequest)->forceFill([
                'employee_id' => $employee->id, 'requested_by' => Auth::id(), 'type' => $type,
                'start_date' => $start, 'end_date' => $end, 'days' => $days, 'reason' => $reason ?: null,
                'status' => LeaveRequest::PENDING,
            ]);
            $request->save();
            $this->activityLogger->log('leave.requested', $request, ['employee_number' => $employee->employee_number, 'type' => $type, 'days' => $days]);

            return $request;
        });
    }

    public function approve(LeaveRequest $request, ?string $note = null): LeaveRequest
    {
        return $this->decide($request, LeaveRequest::APPROVED, $note, function (LeaveRequest $locked, Employee $employee) {
            if ($employee->user_id !== null && $employee->user_id === Auth::id()) {
                throw new BusinessRuleException('You cannot approve your own leave request.');
            }
            if ($locked->type === 'annual') {
                $this->assertWithinAllowance($employee, $locked->start_date->year, (string) $locked->days, $locked->id, true);
            }
        });
    }

    public function reject(LeaveRequest $request, string $note): LeaveRequest
    {
        if (trim($note) === '') {
            throw new BusinessRuleException('A reason is required to reject leave.');
        }

        return $this->decide($request, LeaveRequest::REJECTED, $note, function (LeaveRequest $locked, Employee $employee) {
            if ($employee->user_id !== null && $employee->user_id === Auth::id()) {
                throw new BusinessRuleException('You cannot decide your own leave request.');
            }
        });
    }

    /** Withdraw a request that has not started yet. */
    public function cancel(LeaveRequest $request, ?string $note = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $note) {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            if (! in_array($locked->status, [LeaveRequest::PENDING, LeaveRequest::APPROVED], true)) {
                throw new BusinessRuleException('Only pending or approved leave can be cancelled.');
            }
            if ($locked->start_date->lt(today())) {
                throw new BusinessRuleException('Leave that has already started cannot be cancelled.');
            }

            $locked->forceFill(['status' => LeaveRequest::CANCELLED, 'decision_note' => $note ?: $locked->decision_note])->save();
            $this->activityLogger->log('leave.cancelled', $locked, ['days' => (string) $locked->days]);
            $request->setRawAttributes($locked->getAttributes(), true);

            return $request;
        });
    }

    private function decide(LeaveRequest $request, string $status, ?string $note, callable $checks): LeaveRequest
    {
        return DB::transaction(function () use ($request, $status, $note, $checks) {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $employee = Employee::withTrashed()->lockForUpdate()->findOrFail($locked->employee_id);

            if ($locked->status !== LeaveRequest::PENDING) {
                throw new BusinessRuleException('This request has already been decided or withdrawn.');
            }
            $checks($locked, $employee);

            $locked->forceFill(['status' => $status, 'decided_by' => Auth::id(), 'decided_at' => now(), 'decision_note' => $note ?: null])->save();
            $this->activityLogger->log("leave.{$status}", $locked, ['employee_number' => $employee->employee_number, 'days' => (string) $locked->days]);
            $request->setRawAttributes($locked->getAttributes(), true);

            return $request;
        });
    }

    private function assertNoOverlap(Employee $employee, Carbon $start, Carbon $end): void
    {
        $clash = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::APPROVED])
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->exists();

        if ($clash) {
            throw new BusinessRuleException('These dates overlap with another pending or approved leave request.');
        }
    }

    /** @param bool $alreadyCounted the request itself is pending (approval), so its days are already in "pending" */
    private function assertWithinAllowance(Employee $employee, int $year, string $days, ?int $ignoreId = null, bool $alreadyCounted = false): void
    {
        $balance = $this->balance($employee, $year, $alreadyCounted ? $ignoreId : null);

        if (bccomp($days, $balance['remaining'], 1) > 0) {
            throw new BusinessRuleException("Not enough annual leave: {$days} day(s) requested, {$balance['remaining']} remaining for {$year}.");
        }
    }
}
