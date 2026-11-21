<div>
    <a href="{{ route('reports.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Reports</a>
    <div class="mb-6 mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $definition['title'] }}</h1>
            <p class="text-sm text-slate-500">{{ $result['subtitle'] ?? $definition['description'] }} · Currency {{ $currency }}</p>
        </div>
        @if ($canExport && $result)
            @php $q = $definition['dates'] ? ['from' => $from, 'to' => $to] : []; @endphp
            <div class="flex gap-2">
                <a href="{{ route('reports.export', ['report' => $report, 'format' => 'pdf'] + $q) }}" class="btn-secondary">Download PDF</a>
                <a href="{{ route('reports.export', ['report' => $report, 'format' => 'xlsx'] + $q) }}" class="btn-secondary">Download Excel</a>
            </div>
        @endif
    </div>

    @if ($definition['dates'])
        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <label class="text-sm font-medium text-slate-700">From<input type="date" wire:model.live="from" class="input mt-1"></label>
            <label class="text-sm font-medium text-slate-700">To<input type="date" wire:model.live="to" class="input mt-1"></label>
        </div>
    @endif

    @if (! $result)
        <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">Choose a valid date range (the start must not be after the end).</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>@foreach ($result['columns'] as $col)<th class="px-4 py-3 {{ in_array($col['type'], ['money', 'number'], true) ? 'text-right' : '' }}">{{ $col['label'] }}</th>@endforeach</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($result['rows'] as $i => $row)
                        <tr wire:key="r-{{ $i }}">@foreach ($result['columns'] as $key => $col)
                            <td class="px-4 py-2.5 {{ in_array($col['type'], ['money', 'number'], true) ? 'text-right' : '' }}">{{ $col['type'] === 'money' && isset($row[$key]) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '') }}</td>
                        @endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($result['columns']) }}" class="px-4 py-12 text-center text-slate-500">No data for this report.</td></tr>
                    @endforelse
                </tbody>
                @if ($result['totals'])
                    <tfoot class="bg-slate-50 font-semibold"><tr>@foreach ($result['columns'] as $key => $col)
                        <td class="px-4 py-3 {{ in_array($col['type'], ['money', 'number'], true) ? 'text-right' : '' }}">{{ $col['type'] === 'money' && isset($result['totals'][$key]) ? number_format((float) $result['totals'][$key], 2) : ($result['totals'][$key] ?? '') }}</td>
                    @endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    @endif
</div>
