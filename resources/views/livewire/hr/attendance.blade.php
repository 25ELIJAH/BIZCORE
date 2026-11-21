<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Attendance</h1>
        <p class="text-sm text-slate-500">Mark each person for the day. Saving again for the same day corrects the record.</p>
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
    @error('date')<p class="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <label class="text-sm font-medium text-slate-700">Date<input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="input mt-1"></label>
        <label class="text-sm font-medium text-slate-700">Department<select wire:model.live="department" class="input mt-1"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></label>
        <button type="button" wire:click="markAllPresent" class="btn-secondary">Mark blanks present</button>
    </div>

    <form wire:submit="save">
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Notes</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $e)
                        <tr wire:key="att-{{ $e->id }}">
                            <td class="px-4 py-2"><div class="font-medium">{{ $e->fullName() }}</div><div class="text-xs text-slate-500">{{ $e->employee_number }}</div></td>
                            <td class="px-4 py-2"><select wire:model="rows.{{ $e->id }}.status" aria-label="Status for {{ $e->fullName() }}" class="input w-36"><option value="">—</option>@foreach ($statuses as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></td>
                            <td class="px-4 py-2"><input type="time" wire:model="rows.{{ $e->id }}.check_in" aria-label="Check-in" class="input w-28">@error("rows.{$e->id}.check_in")<p class="text-xs text-red-600">{{ $message }}</p>@enderror</td>
                            <td class="px-4 py-2"><input type="time" wire:model="rows.{{ $e->id }}.check_out" aria-label="Check-out" class="input w-28">@error("rows.{$e->id}.check_out")<p class="text-xs text-red-600">{{ $message }}</p>@enderror</td>
                            <td class="px-4 py-2"><input type="text" wire:model="rows.{{ $e->id }}.notes" aria-label="Notes" class="input" maxlength="255"></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No active employees for this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($employees->isNotEmpty())
            <div class="mt-4 flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled">Save attendance</button></div>
        @endif
    </form>
</div>
