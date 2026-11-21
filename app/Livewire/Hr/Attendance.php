<?php

namespace App\Livewire\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Daily attendance sheet: pick a date, mark each active employee, save the whole day. */
#[Layout('layouts.app')]
#[Title('Attendance')]
class Attendance extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public ?int $department = null;

    /** @var array<int, array{status: string, check_in: string, check_out: string, notes: string}> keyed by employee id */
    public array $rows = [];

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('attendance.manage');
        $this->date = $this->date ?: today()->toDateString();
        $this->loadRows();
    }

    public function updatedDate(): void
    {
        $this->notice = '';
        $this->loadRows();
    }

    public function updatedDepartment(): void
    {
        $this->loadRows();
    }

    private function employeesForDay()
    {
        return Employee::where('status', Employee::ACTIVE)
            ->whereDate('hire_date', '<=', $this->date)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->orderBy('first_name')->orderBy('last_name')->get();
    }

    private function loadRows(): void
    {
        $this->rows = [];
        if (! $this->validDate()) {
            return;
        }
        $existing = AttendanceRecord::whereDate('date', $this->date)->get()->keyBy('employee_id');
        foreach ($this->employeesForDay() as $e) {
            $r = $existing->get($e->id);
            $this->rows[$e->id] = [
                'status' => $r?->status ?? '', 'check_in' => $r?->check_in ? substr($r->check_in, 0, 5) : '',
                'check_out' => $r?->check_out ? substr($r->check_out, 0, 5) : '', 'notes' => (string) $r?->notes,
            ];
        }
    }

    private function validDate(): bool
    {
        return (bool) strtotime($this->date) && $this->date <= today()->toDateString();
    }

    public function markAllPresent(): void
    {
        $this->authorize('attendance.manage');
        foreach ($this->rows as $id => $row) {
            if ($row['status'] === '') {
                $this->rows[$id]['status'] = 'present';
            }
        }
    }

    public function save(AttendanceService $service): void
    {
        $this->authorize('attendance.manage');
        $this->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows.*.status' => ['nullable', 'in:'.implode(',', array_keys(AttendanceRecord::STATUSES))],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ], [], ['rows.*.check_in' => 'check-in', 'rows.*.check_out' => 'check-out']);

        try {
            $count = $service->recordDay($this->date, $this->rows);
        } catch (BusinessRuleException $e) {
            $this->addError('date', $e->getMessage());

            return;
        }

        $this->notice = $count === 0 ? 'Nothing to save: no status was chosen.' : "Attendance saved for {$count} employee(s).";
    }

    public function render()
    {
        return view('livewire.hr.attendance', [
            'employees' => $this->validDate() ? $this->employeesForDay() : collect(),
            'departments' => Department::orderBy('name')->get(),
            'statuses' => AttendanceRecord::STATUSES,
        ]);
    }
}
