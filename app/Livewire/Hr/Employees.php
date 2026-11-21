<?php

namespace App\Livewire\Hr;

use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Employees')]
class Employees extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $department = null;

    /** active | terminated */
    #[Url]
    public string $status = 'active';

    public string $notice = '';

    // employee form
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $phone = '';

    public string $jobTitle = '';

    public string $hireDate = '';

    public ?int $departmentId = null;

    public ?int $userId = null;

    public int $annualLeaveDays = 21;

    // sensitive: only populated / saved for users with employees.view_sensitive
    public string $nationalId = '';

    public string $salary = '';

    public string $bankAccount = '';

    // terminate
    public ?int $terminateId = null;

    public string $terminateDate = '';

    public string $terminateReason = '';

    // departments
    public bool $showDepartments = false;

    public string $newDepartment = '';

    public function mount(): void
    {
        $this->authorize('employees.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'department', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('employees.manage');
        $this->resetValidation();
        $this->reset('editingId', 'firstName', 'lastName', 'email', 'phone', 'jobTitle', 'departmentId', 'userId', 'nationalId', 'salary', 'bankAccount');
        $this->hireDate = today()->toDateString();
        $this->annualLeaveDays = 21;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('employees.manage');
        $employee = Employee::where('status', Employee::ACTIVE)->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $employee->id;
        $this->firstName = $employee->first_name;
        $this->lastName = $employee->last_name;
        $this->email = (string) $employee->email;
        $this->phone = (string) $employee->phone;
        $this->jobTitle = (string) $employee->job_title;
        $this->hireDate = $employee->hire_date->toDateString();
        $this->departmentId = $employee->department_id;
        $this->userId = $employee->user_id;
        $this->annualLeaveDays = (int) $employee->annual_leave_days;
        // Sensitive values never reach the browser unless the user may see them.
        $this->nationalId = Gate::allows('employees.view_sensitive') ? (string) $employee->getAttribute('national_id') : '';
        $this->salary = Gate::allows('employees.view_sensitive') ? (string) $employee->getAttribute('salary') : '';
        $this->bankAccount = Gate::allows('employees.view_sensitive') ? (string) $employee->getAttribute('bank_account') : '';
        $this->showForm = true;
    }

    public function save(EmployeeService $service): void
    {
        $this->authorize('employees.manage');
        $this->validate([
            'firstName' => ['required', 'string', 'max:100'], 'lastName' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
            'jobTitle' => ['nullable', 'string', 'max:100'], 'hireDate' => ['required', 'date'],
            'annualLeaveDays' => ['required', 'integer', 'min:0', 'max:365'],
            'nationalId' => ['nullable', 'string', 'max:50'], 'salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'bankAccount' => ['nullable', 'string', 'max:60'],
        ], [], ['firstName' => 'first name', 'lastName' => 'last name', 'hireDate' => 'hire date', 'annualLeaveDays' => 'annual leave days']);

        $canSensitive = Gate::allows('employees.view_sensitive');

        try {
            $service->save([
                'first_name' => $this->firstName, 'last_name' => $this->lastName, 'email' => $this->email, 'phone' => $this->phone,
                'job_title' => $this->jobTitle, 'hire_date' => $this->hireDate, 'department_id' => $this->departmentId,
                'user_id' => $this->userId, 'annual_leave_days' => $this->annualLeaveDays,
                'national_id' => $this->nationalId, 'salary' => $this->salary, 'bank_account' => $this->bankAccount,
            ], $this->editingId ? Employee::findOrFail($this->editingId) : null, $canSensitive);
        } catch (BusinessRuleException $e) {
            $this->addError('firstName', $e->getMessage());

            return;
        }

        $this->notice = $this->editingId ? 'Employee updated.' : 'Employee added.';
        $this->closeModals();
    }

    public function openTerminate(int $id): void
    {
        $this->authorize('employees.manage');
        Employee::where('status', Employee::ACTIVE)->findOrFail($id);
        $this->resetValidation();
        $this->terminateId = $id;
        $this->terminateDate = today()->toDateString();
        $this->terminateReason = '';
    }

    public function terminate(EmployeeService $service): void
    {
        $this->authorize('employees.manage');
        $this->validate(['terminateDate' => ['required', 'date'], 'terminateReason' => ['nullable', 'string', 'max:255']], [], ['terminateDate' => 'date']);

        try {
            $service->terminate(Employee::findOrFail($this->terminateId), $this->terminateDate, $this->terminateReason ?: null);
        } catch (BusinessRuleException $e) {
            $this->addError('terminateDate', $e->getMessage());

            return;
        }

        $this->notice = 'Employee terminated. Their history is kept.';
        $this->closeModals();
    }

    public function addDepartment(EmployeeService $service): void
    {
        $this->authorize('employees.manage');
        $this->validate(['newDepartment' => ['required', 'string', 'max:100']], [], ['newDepartment' => 'department name']);

        try {
            $service->saveDepartment($this->newDepartment);
        } catch (BusinessRuleException $e) {
            $this->addError('newDepartment', $e->getMessage());

            return;
        }

        $this->newDepartment = '';
    }

    public function deleteDepartment(int $id, EmployeeService $service): void
    {
        $this->authorize('employees.manage');

        try {
            $service->deleteDepartment(Department::findOrFail($id));
        } catch (BusinessRuleException $e) {
            $this->addError('newDepartment', $e->getMessage());
        }
    }

    public function closeModals(): void
    {
        $this->showForm = $this->showDepartments = false;
        $this->terminateId = null;
    }

    public function render()
    {
        $employees = Employee::query()->with('department')
            ->where('status', $this->status === 'terminated' ? Employee::TERMINATED : Employee::ACTIVE)
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term)->orWhere('job_title', 'like', $term));
            })
            ->orderBy('first_name')->orderBy('last_name')->paginate(15);

        return view('livewire.hr.employees', [
            'employees' => $employees,
            'departments' => Department::withCount('employees')->orderBy('name')->get(),
            'users' => Gate::allows('employees.manage')
                ? User::whereIn('id', User::query()->whereHas('companies', fn ($q) => $q->whereKey(app(CurrentCompany::class)->id()))->select('id'))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canSensitive' => Gate::allows('employees.view_sensitive'),
        ]);
    }
}
