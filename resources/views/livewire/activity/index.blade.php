<div>
    <div class="mb-6">
        <h1>Activity</h1>
        <p class="mt-1 text-sm text-slate-500">Who did what in this company, newest first.</p>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Search what happened" aria-label="Search activity">
        <select wire:model.live="user" class="input" aria-label="Person">
            <option value="">Everyone</option>
            @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="from" class="input" aria-label="From date">
        <input type="date" wire:model.live="to" class="input" aria-label="To date">
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">What</th><th class="px-4 py-3">Details</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-slate-600">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3"><span class="badge bg-brand-soft text-slate-700">{{ ucfirst(str_replace(['.', '_'], ' ', $log->action)) }}</span></td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $log->description ?: ($log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '') }}
                            @if (! empty($log->properties['to']))<span class="text-slate-400"> &middot; {{ $log->properties['to'] }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No activity matches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
