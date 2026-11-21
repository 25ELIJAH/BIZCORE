<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /** One record per employee per day: recording again for the same day corrects it. */
    public function record(Employee $employee, string|Carbon $date, string $status, ?string $checkIn = null, ?string $checkOut = null, ?string $notes = null): AttendanceRecord
    {
        $date = Carbon::parse($date)->startOfDay();

        if (! array_key_exists($status, AttendanceRecord::STATUSES)) {
            throw new BusinessRuleException('Unknown attendance status.');
        }
        if ($date->gt(today())) {
            throw new BusinessRuleException('Attendance cannot be recorded for a future date.');
        }
        if ($date->lt($employee->hire_date)) {
            throw new BusinessRuleException("{$employee->fullName()} had not started on {$date->toDateString()}.");
        }
        if ($employee->termination_date && $date->gt($employee->termination_date)) {
            throw new BusinessRuleException("{$employee->fullName()} had left by {$date->toDateString()}.");
        }
        if ($checkIn && $checkOut && strcmp($checkOut, $checkIn) <= 0) {
            throw new BusinessRuleException('Check-out must be after check-in.');
        }

        return DB::transaction(function () use ($employee, $date, $status, $checkIn, $checkOut, $notes) {
            $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('date', $date)->lockForUpdate()->first()
                ?? (new AttendanceRecord)->forceFill(['employee_id' => $employee->id, 'date' => $date]);
            $isNew = ! $record->exists;

            $record->forceFill([
                'status' => $status, 'check_in' => $checkIn ?: null, 'check_out' => $checkOut ?: null,
                'notes' => $notes ?: null, 'recorded_by' => Auth::id(),
            ])->save();

            $this->activityLogger->log($isNew ? 'attendance.recorded' : 'attendance.corrected', $record, [
                'employee_number' => $employee->employee_number, 'date' => $date->toDateString(), 'status' => $status,
            ]);

            return $record;
        });
    }

    /**
     * Record a whole day at once. All or nothing.
     *
     * @param  array<int, array{status: string, check_in?: ?string, check_out?: ?string, notes?: ?string}>  $rows  keyed by employee id
     * @return int number of records written
     */
    public function recordDay(string|Carbon $date, array $rows): int
    {
        return DB::transaction(function () use ($date, $rows) {
            $count = 0;
            foreach ($rows as $employeeId => $row) {
                if (($row['status'] ?? '') === '') {
                    continue; // left blank: nothing recorded for this person
                }
                $employee = Employee::findOrFail($employeeId);
                $this->record($employee, $date, $row['status'], $row['check_in'] ?? null, $row['check_out'] ?? null, $row['notes'] ?? null);
                $count++;
            }

            return $count;
        });
    }
}
