<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /** Attributes only users with employees.view_sensitive may read or change. */
    public const SENSITIVE = ['national_id', 'salary', 'bank_account'];

    public function __construct(private DocumentNumberService $numbers, private ActivityLogger $activityLogger, private CurrentCompany $currentCompany) {}

    public function saveDepartment(string $name, ?Department $department = null): Department
    {
        $name = trim($name);
        if ($name === '') {
            throw new BusinessRuleException('Department name is required.');
        }
        if (Department::where('name', $name)->when($department, fn ($q) => $q->whereKeyNot($department->id))->exists()) {
            throw new BusinessRuleException("A department called \"{$name}\" already exists.");
        }

        $department ??= new Department;
        $isNew = ! $department->exists;
        $department->forceFill(['name' => $name])->save();
        $this->activityLogger->log($isNew ? 'department.created' : 'department.updated', $department, ['name' => $name]);

        return $department;
    }

    public function deleteDepartment(Department $department): void
    {
        if ($department->employees()->withTrashed()->exists()) {
            throw new BusinessRuleException('This department has employees. Move them to another department first.');
        }
        $department->delete();
        $this->activityLogger->log('department.deleted', $department, ['name' => $department->name]);
    }

    /**
     * Create or update an employee.
     * Sensitive attributes are written only when $canEditSensitive is true; otherwise they are ignored (kept as they were).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?Employee $employee = null, bool $canEditSensitive = false): Employee
    {
        return DB::transaction(function () use ($data, $employee, $canEditSensitive) {
            if ($employee) {
                $employee = Employee::query()->lockForUpdate()->findOrFail($employee->getKey());
                if (! $employee->isActive()) {
                    throw new BusinessRuleException('Terminated employees cannot be edited.');
                }
            }

            $first = trim((string) ($data['first_name'] ?? ''));
            $last = trim((string) ($data['last_name'] ?? ''));
            if ($first === '' || $last === '') {
                throw new BusinessRuleException('First and last name are required.');
            }
            if (! empty($data['email']) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new BusinessRuleException('Email address is not valid.');
            }

            $hire = Carbon::parse($data['hire_date'] ?? today());
            if ($hire->gt(today()->addYear())) {
                throw new BusinessRuleException('Hire date is too far in the future.');
            }

            $leaveDays = (int) ($data['annual_leave_days'] ?? 21);
            if ($leaveDays < 0 || $leaveDays > 365) {
                throw new BusinessRuleException('Annual leave days must be between 0 and 365.');
            }

            $departmentId = null;
            if (! empty($data['department_id'])) {
                $departmentId = Department::find($data['department_id'])?->id
                    ?? throw new BusinessRuleException('Choose a valid department.');
            }

            $userId = null;
            if (! empty($data['user_id'])) {
                $userId = $this->linkableUser((int) $data['user_id'], $employee)->id;
            }

            $attributes = [
                'department_id' => $departmentId, 'user_id' => $userId,
                'first_name' => $first, 'last_name' => $last,
                'email' => ($data['email'] ?? null) ?: null, 'phone' => ($data['phone'] ?? null) ?: null,
                'job_title' => ($data['job_title'] ?? null) ?: null,
                'hire_date' => $hire, 'annual_leave_days' => $leaveDays,
            ];

            if ($canEditSensitive) {
                $salary = $data['salary'] ?? null;
                if ($salary !== null && $salary !== '') {
                    if (! is_numeric((string) $salary) || Money::compare(Money::round($salary), '0.00') < 0) {
                        throw new BusinessRuleException('Salary must be zero or more.');
                    }
                    $salary = Money::round($salary);
                } else {
                    $salary = null;
                }
                $attributes += [
                    'national_id' => ($data['national_id'] ?? null) ?: null,
                    'bank_account' => ($data['bank_account'] ?? null) ?: null,
                    'salary' => $salary,
                ];
            }

            $employee ??= (new Employee)->forceFill(['employee_number' => $this->numbers->next('employee', 'EMP'), 'status' => Employee::ACTIVE]);
            $isNew = ! $employee->exists;
            $employee->forceFill($attributes)->save();

            // Never log the sensitive values themselves, only that they were touched.
            $this->activityLogger->log($isNew ? 'employee.created' : 'employee.updated', $employee, [
                'employee_number' => $employee->employee_number, 'sensitive_fields_changed' => $canEditSensitive,
            ]);

            return $employee;
        });
    }

    public function terminate(Employee $employee, string|Carbon $date, ?string $reason = null): Employee
    {
        return DB::transaction(function () use ($employee, $date, $reason) {
            $locked = Employee::query()->lockForUpdate()->findOrFail($employee->getKey());
            if (! $locked->isActive()) {
                throw new BusinessRuleException('This employee is already terminated.');
            }
            $date = Carbon::parse($date);
            if ($date->lt($locked->hire_date)) {
                throw new BusinessRuleException('Termination date cannot be before the hire date.');
            }

            $locked->forceFill(['status' => Employee::TERMINATED, 'termination_date' => $date])->save();
            // Nothing is left waiting for an approver once the person has left.
            $cancelled = LeaveRequest::where('employee_id', $locked->id)->where('status', LeaveRequest::PENDING)
                ->update(['status' => LeaveRequest::CANCELLED, 'decision_note' => 'Employee terminated']);

            $this->activityLogger->log('employee.terminated', $locked, ['employee_number' => $locked->employee_number, 'date' => $date->toDateString(), 'reason' => $reason, 'pending_leave_cancelled' => $cancelled]);
            $employee->setRawAttributes($locked->getAttributes(), true);

            return $employee;
        });
    }

    private function linkableUser(int $userId, ?Employee $current): User
    {
        $user = User::find($userId);
        if (! $user || ! $user->companies()->whereKey($this->currentCompany->id())->exists()) {
            throw new BusinessRuleException('That user does not belong to this company.');
        }
        $taken = Employee::withTrashed()->where('user_id', $user->id)->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists();
        if ($taken) {
            throw new BusinessRuleException('That user is already linked to another employee.');
        }

        return $user;
    }
}
