<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Leave</h1>
            <p class="text-sm text-slate-500">{{ $seesAll ? 'All leave requests in this company.' : 'Your leave requests.' }} Days are working days (Mon–Fri).</p>
        </div>
        @if (auth()->user()->can('leave.request') || auth()->user()->can('employees.manage'))
            <button type="button" wire:click="openRequest" class="btn-primary">Request leave</button>
        @endif
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
    @error('cancel')<p class="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    @if ($balance)
        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Annual allowance {{ $year }}</p><p class="mt-1 text-2xl font-semibold">{{ $balance['allowance'] }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Taken</p><p class="mt-1 text-2xl font-semibold">{{ $balance['used'] }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Pending</p><p class="mt-1 text-2xl font-semibold">{{ $balance['pending'] }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Remaining</p><p class="mt-1 text-2xl font-semibold">{{ $balance['remaining'] }}</p></div>
        </div>
    @endif

    <div class="mb-4"><select wire:model.live="status" aria-label="Status" class="input w-48"><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></select></div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3 text-right">Days</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($requests as $r)
                    @php
                        $badge = match ($r->status) { 'approved' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-red-100 text-red-700', 'cancelled' => 'bg-slate-200 text-slate-600', default => 'bg-amber-100 text-amber-800' };
                        $isMine = $r->employee?->user_id === $userId;
                    @endphp
                    <tr wire:key="lv-{{ $r->id }}">
                        <td class="px-4 py-3 font-medium">{{ $r->employee?->fullName() }}@if ($r->reason)<div class="text-xs font-normal text-slate-500">{{ $r->reason }}</div>@endif</td>
                        <td class="px-4 py-3">{{ $types[$r->type] ?? $r->type }}</td>
                        <td class="px-4 py-3">{{ $r->start_date->format('d M Y') }} – {{ $r->end_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">{{ $r->days }}</td>
                        <td class="px-4 py-3"><span class="badge {{ $badge }}">{{ ucfirst($r->status) }}</span>@if ($r->decision_note)<div class="text-xs text-slate-500">{{ $r->decision_note }}</div>@endif</td>
                        <td class="px-4 py-3 text-right">
                            @if ($r->status === 'pending')
                                @can('leave.approve')
                                    <button type="button" wire:click="openDecision({{ $r->id }}, 'approve')" class="text-emerald-700 hover:underline">Approve</button>
                                    <button type="button" wire:click="openDecision({{ $r->id }}, 'reject')" class="ml-3 text-red-600 hover:underline">Reject</button>
                                @endcan
                            @endif
                            @if (in_array($r->status, ['pending', 'approved'], true) && $r->start_date->gte(today()) && ($isMine || auth()->user()->can('employees.manage')))
                                <button type="button" wire:click="cancel({{ $r->id }})" wire:confirm="Cancel this leave request?" class="ml-3 text-slate-600 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No leave requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>

    @if ($showForm)
        <x-ui.modal title="Request leave" close="closeModals">
            <form wire:submit="submit" class="space-y-4">
                @if ($employees->isNotEmpty())
                    <x-ui.field label="Employee" name="employeeId"><select id="employeeId" wire:model="employeeId" class="input"><option value="">Choose…</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->fullName() }}</option>@endforeach</select></x-ui.field>
                @else
                    @error('employeeId')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @unless ($employeeId)<p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Your login is not linked to an employee record. Ask HR to link it.</p>@endunless
                @endif
                <x-ui.field label="Type" name="type"><select id="type" wire:model="type" class="input">@foreach ($types as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="From" name="startDate"><input id="startDate" type="date" wire:model="startDate" class="input"></x-ui.field>
                    <x-ui.field label="To" name="endDate"><input id="endDate" type="date" wire:model="endDate" class="input"></x-ui.field>
                </div>
                <x-ui.field label="Reason" name="reason"><input id="reason" type="text" wire:model="reason" class="input" maxlength="255"></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary" wire:loading.attr="disabled">Submit request</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($decideId)
        <x-ui.modal :title="$decision === 'approve' ? 'Approve leave' : 'Reject leave'" close="closeModals">
            <form wire:submit="decide" class="space-y-4">
                <x-ui.field :label="$decision === 'approve' ? 'Note (optional)' : 'Reason (required)'" name="note"><input id="note" type="text" wire:model="note" class="input" maxlength="255" autofocus></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Back</button><button type="submit" class="{{ $decision === 'approve' ? 'btn-primary' : 'btn-danger' }}">{{ $decision === 'approve' ? 'Approve' : 'Reject' }}</button></div>
            </form>
        </x-ui.modal>
    @endif
</div>
