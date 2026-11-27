<?php

namespace App\Livewire\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Leave requests. Approvers and HR see everyone's; everybody else sees only their own.
 * A person with a linked employee record can request leave for themselves; HR (employees.manage) can request for anyone.
 */
#[Layout('layouts.app')]
#[Title('Leave')]
class Leave extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public string $notice = '';

    // request modal
    public bool $showForm = false;

    public ?int $employeeId = null;

    public string $type = 'annual';

    public string $startDate = '';

    public string $endDate = '';

    public string $reason = '';

    // decision modal
    public ?int $decideId = null;

    /** approve | reject */
    public string $decision = 'approve';

    public string $note = '';

    public function mount(): void
    {
        abort_unless(Gate::any(['leave.request', 'leave.approve', 'employees.manage']), 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    private function ownEmployee(): ?Employee
    {
        $own = Employee::where('user_id', Auth::id())->where('status', Employee::ACTIVE)->first();

        // HR often creates the employee record first. If it carries this person's email and is not yet linked
        // to any login, link it now so they can ask for leave without waiting for HR to do it by hand.
        if (! $own && filled(Auth::user()->email)) {
            $own = Employee::whereNull('user_id')->where('status', Employee::ACTIVE)->where('email', Auth::user()->email)->first();
            $own?->forceFill(['user_id' => Auth::id()])->save();
        }

        // Still nothing: give this person a basic employee record of their own, so leave works from day one.
        // HR can complete the details (department, job title, pay) later under Employees.
        if (! $own && Gate::allows('leave.request') && ! $this->seesAll()) {
            try {
                $user = Auth::user();
                $parts = preg_split('/\s+/', trim($user->name), 2);
                $own = app(EmployeeService::class)->save([
                    'first_name' => $parts[0] ?: 'Staff', 'last_name' => $parts[1] ?? '-',
                    'email' => $user->email, 'user_id' => $user->id, 'hire_date' => today(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $own;
    }

    private function seesAll(): bool
    {
        return Gate::any(['leave.approve', 'employees.manage']);
    }

    public function openRequest(): void
    {
        abort_unless(Gate::any(['leave.request', 'employees.manage']), 403);
        $this->resetValidation();
        $this->reset('reason');
        $this->type = 'annual';
        $this->startDate = $this->endDate = today()->toDateString();
        $this->employeeId = Gate::allows('employees.manage') ? null : $this->ownEmployee()?->id;
        $this->showForm = true;
    }

    public function submit(LeaveService $service): void
    {
        abort_unless(Gate::any(['leave.request', 'employees.manage']), 403);
        $this->validate([
            'employeeId' => ['required', 'integer'], 'type' => ['required', 'in:'.implode(',', array_keys(LeaveRequest::TYPES))],
            'startDate' => ['required', 'date'], 'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['employeeId.required' => 'Choose an employee (your account is not linked to an employee record).'], ['startDate' => 'start date', 'endDate' => 'end date', 'employeeId' => 'employee']);

        // Staff may only request for themselves; HR may request for anyone in the company.
        $employee = Employee::find($this->employeeId);
        if (! $employee || (! Gate::allows('employees.manage') && $employee->user_id !== Auth::id())) {
            $this->addError('employeeId', 'You can only request leave for yourself.');

            return;
        }

        try {
            $service->request($employee, $this->type, $this->startDate, $this->endDate, $this->reason ?: null);
        } catch (BusinessRuleException $e) {
            $this->addError('startDate', $e->getMessage());

            return;
        }

        $this->notice = 'Leave request submitted.';
        $this->closeModals();
    }

    public function openDecision(int $id, string $decision): void
    {
        $this->authorize('leave.approve');
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        LeaveRequest::where('status', LeaveRequest::PENDING)->findOrFail($id);
        $this->resetValidation();
        $this->decideId = $id;
        $this->decision = $decision;
        $this->note = '';
    }

    public function decide(LeaveService $service): void
    {
        $this->authorize('leave.approve');
        $this->validate(['note' => [$this->decision === 'reject' ? 'required' : 'nullable', 'string', 'max:255']], [], ['note' => 'reason']);
        $request = LeaveRequest::findOrFail($this->decideId);

        try {
            $this->decision === 'approve' ? $service->approve($request, $this->note ?: null) : $service->reject($request, $this->note);
        } catch (BusinessRuleException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->notice = $this->decision === 'approve' ? 'Leave approved.' : 'Leave rejected.';
        $this->closeModals();
    }

    public function cancel(int $id, LeaveService $service): void
    {
        $request = LeaveRequest::with('employee')->findOrFail($id);
        $mine = $request->employee?->user_id === Auth::id();
        abort_unless($mine || Gate::allows('employees.manage'), 403);

        try {
            $service->cancel($request);
        } catch (BusinessRuleException $e) {
            $this->addError('cancel', $e->getMessage());

            return;
        }

        $this->notice = 'Leave request cancelled.';
    }

    public function closeModals(): void
    {
        $this->showForm = false;
        $this->decideId = null;
    }

    public function render(LeaveService $service)
    {
        $own = $this->ownEmployee();
        $requests = LeaveRequest::query()->with(['employee', 'decider'])
            ->when(! $this->seesAll(), fn ($q) => $q->where('employee_id', $own?->id ?? 0))
            ->when(array_key_exists($this->status, ['pending' => 1, 'approved' => 1, 'rejected' => 1, 'cancelled' => 1]), fn ($q) => $q->where('status', $this->status))
            ->latest('start_date')->latest('id')->paginate(15);

        return view('livewire.hr.leave', [
            'requests' => $requests,
            'balance' => $own ? $service->balance($own, today()->year) : null,
            'employees' => Gate::allows('employees.manage') ? Employee::where('status', Employee::ACTIVE)->orderBy('first_name')->get() : collect(),
            'types' => LeaveRequest::TYPES,
            'seesAll' => $this->seesAll(),
            'userId' => Auth::id(),
            'year' => today()->year,
        ]);
    }
}
