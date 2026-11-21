<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Employees</h1>
            <p class="text-sm text-slate-500">Staff records. Salary, national ID and bank details are visible only to users with sensitive-data access.</p>
        </div>
        @can('employees.manage')
            <div class="flex gap-2">
                <button type="button" wire:click="$set('showDepartments', true)" class="btn-secondary">Departments</button>
                <button type="button" wire:click="create" class="btn-primary">Add employee</button>
            </div>
        @endcan
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, number or job title" aria-label="Search employees" class="input">
        <select wire:model.live="department" aria-label="Department" class="input"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
        <select wire:model.live="status" aria-label="Status" class="input"><option value="active">Active</option><option value="terminated">Terminated</option></select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">No.</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Job title</th><th class="px-4 py-3">Hired</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $e)
                    <tr wire:key="emp-{{ $e->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $e->employee_number }}</td>
                        <td class="px-4 py-3 font-medium">{{ $e->fullName() }}<div class="text-xs font-normal text-slate-500">{{ $e->email }}</div></td>
                        <td class="px-4 py-3">{{ $e->department?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $e->job_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $e->hire_date->format('Y-m-d') }}@if ($e->termination_date)<div class="text-xs text-red-600">Left {{ $e->termination_date->format('Y-m-d') }}</div>@endif</td>
                        <td class="px-4 py-3 text-right">
                            @can('employees.manage')
                                @if ($e->isActive())
                                    <button type="button" wire:click="edit({{ $e->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="openTerminate({{ $e->id }})" class="ml-3 text-red-600 hover:underline">Terminate</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No employees match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>

    @if ($showForm)
        <x-ui.modal :title="$editingId ? 'Edit employee' : 'Add employee'" close="closeModals" maxWidth="max-w-2xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="First name" name="firstName"><input id="firstName" type="text" wire:model="firstName" class="input" maxlength="100" autofocus></x-ui.field>
                <x-ui.field label="Last name" name="lastName"><input id="lastName" type="text" wire:model="lastName" class="input" maxlength="100"></x-ui.field>
                <x-ui.field label="Email" name="email"><input id="email" type="email" wire:model="email" class="input"></x-ui.field>
                <x-ui.field label="Phone" name="phone"><input id="phone" type="text" wire:model="phone" class="input" maxlength="50"></x-ui.field>
                <x-ui.field label="Job title" name="jobTitle"><input id="jobTitle" type="text" wire:model="jobTitle" class="input" maxlength="100"></x-ui.field>
                <x-ui.field label="Department" name="departmentId"><select id="departmentId" wire:model="departmentId" class="input"><option value="">None</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Hire date" name="hireDate"><input id="hireDate" type="date" wire:model="hireDate" class="input"></x-ui.field>
                <x-ui.field label="Annual leave days" name="annualLeaveDays"><input id="annualLeaveDays" type="number" min="0" max="365" wire:model="annualLeaveDays" class="input"></x-ui.field>
                <x-ui.field label="Login account" name="userId" hint="Lets this person request their own leave." class="sm:col-span-2"><select id="userId" wire:model="userId" class="input"><option value="">No login account</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></x-ui.field>
                @if ($canSensitive)
                    <div class="grid gap-4 rounded-lg bg-amber-50 p-3 sm:col-span-2 sm:grid-cols-3">
                        <x-ui.field label="National ID" name="nationalId"><input id="nationalId" type="text" wire:model="nationalId" class="input" maxlength="50" autocomplete="off"></x-ui.field>
                        <x-ui.field label="Monthly salary" name="salary"><input id="salary" type="number" step="0.01" min="0" wire:model="salary" class="input"></x-ui.field>
                        <x-ui.field label="Bank account" name="bankAccount"><input id="bankAccount" type="text" wire:model="bankAccount" class="input" maxlength="60" autocomplete="off"></x-ui.field>
                    </div>
                @endif
                <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($terminateId)
        <x-ui.modal title="Terminate employee" close="closeModals">
            <form wire:submit="terminate" class="space-y-4">
                <p class="text-sm text-slate-600">The record and its history are kept. Pending leave requests are cancelled.</p>
                <x-ui.field label="Last day" name="terminateDate"><input id="terminateDate" type="date" wire:model="terminateDate" class="input"></x-ui.field>
                <x-ui.field label="Reason" name="terminateReason"><input id="terminateReason" type="text" wire:model="terminateReason" class="input" maxlength="255"></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-danger">Terminate</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showDepartments)
        <x-ui.modal title="Departments" close="closeModals">
            <form wire:submit="addDepartment" class="mb-4 flex gap-2">
                <input type="text" wire:model="newDepartment" placeholder="New department name" aria-label="New department name" class="input" maxlength="100">
                <button type="submit" class="btn-primary">Add</button>
            </form>
            @error('newDepartment')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($departments as $d)
                    <li wire:key="dep-{{ $d->id }}" class="flex items-center justify-between py-2"><span>{{ $d->name }} <span class="text-xs text-slate-500">({{ $d->employees_count }})</span></span><button type="button" wire:click="deleteDepartment({{ $d->id }})" class="text-red-600 hover:underline">Delete</button></li>
                @empty
                    <li class="py-4 text-center text-slate-500">No departments yet.</li>
                @endforelse
            </ul>
        </x-ui.modal>
    @endif
</div>
