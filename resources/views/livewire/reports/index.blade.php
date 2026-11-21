<div>
    <div class="mb-6"><h1 class="text-2xl font-semibold">Reports</h1><p class="text-sm text-slate-500">Only the reports your role allows are listed.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($reports as $slug => $def)
            <a href="{{ route('reports.show', $slug) }}" class="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm">
                <h2 class="font-semibold text-slate-900">{{ $def['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $def['description'] }}</p>
            </a>
        @endforeach
    </div>
    @if ($reports === [])<p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">No reports are available for your role.</p>@endif
</div>
