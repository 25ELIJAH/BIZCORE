<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Income and expenses</h1>
            <p class="text-sm text-slate-500">Customer and supplier payments appear here automatically. Record everything else manually.</p>
        </div>
        <div class="flex gap-2">
            @can('finance.categories.manage')<a href="{{ route('finance.categories') }}" class="btn-secondary">Categories</a>@endcan
            @can('finance.manage')<button type="button" wire:click="create" class="btn-primary">Record entry</button>@endcan
        </div>
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Income</p><p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $currency }} {{ number_format((float) $summary['income'], 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Expenses</p><p class="mt-1 text-2xl font-semibold text-red-700">{{ $currency }} {{ number_format((float) $summary['expense'], 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Net</p><p class="mt-1 text-2xl font-semibold {{ bccomp($summary['net'], '0', 2) < 0 ? 'text-red-700' : '' }}">{{ $currency }} {{ number_format((float) $summary['net'], 2) }}</p></div>
    </div>

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Description or reference" aria-label="Search entries" class="input lg:col-span-2">
        <select wire:model.live="type" aria-label="Type" class="input"><option value="all">Income and expenses</option><option value="income">Income</option><option value="expense">Expenses</option></select>
        <select wire:model.live="category" aria-label="Category" class="input"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type }})</option>@endforeach</select>
        <input type="date" wire:model.live="from" aria-label="From date" class="input">
        <input type="date" wire:model.live="to" aria-label="To date" class="input">
        <label class="flex items-center gap-2 text-sm text-slate-600 lg:col-span-2"><input type="checkbox" wire:model.live="showVoided" class="rounded border-slate-300"> Show voided entries</label>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Category</th><th class="px-4 py-3 text-right">Amount ({{ $currency }})</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr wire:key="fe-{{ $entry->id }}" class="{{ $entry->isVoided() ? 'text-slate-400' : '' }}">
                        <td class="px-4 py-3">{{ $entry->entry_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $entry->isVoided() ? 'line-through' : '' }}">{{ $entry->description }}</span>
                            @if ($entry->reference)<span class="text-xs text-slate-500"> · {{ $entry->reference }}</span>@endif
                            @if ($entry->isDerived())<span class="badge ml-1 bg-slate-100 text-slate-600">From payment</span>@endif
                            @if ($entry->isVoided())<span class="badge ml-1 bg-slate-200 text-slate-700">Voided: {{ $entry->void_reason }}</span>@endif
                        </td>
                        <td class="px-4 py-3">{{ $entry->category?->name }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $entry->isVoided() ? '' : ($entry->type === 'income' ? 'text-emerald-700' : 'text-red-700') }}">{{ $entry->type === 'income' ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('finance.manage')
                                @if (! $entry->isDerived() && ! $entry->isVoided())
                                    <button type="button" wire:click="edit({{ $entry->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="openVoid({{ $entry->id }})" class="ml-3 text-red-600 hover:underline">Void</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No entries for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>

    @if ($showForm)
        <x-ui.modal :title="$editingId ? 'Edit entry' : 'Record entry'" close="closeModals">
            <form wire:submit="save" class="space-y-4">
                <x-ui.field label="Category" name="categoryId">
                    <select id="categoryId" wire:model="categoryId" class="input">
                        <option value="">Choose a category…</option>
                        @foreach ($categories->where('is_active', true)->where('is_system', false) as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type }})</option>@endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Amount" name="amount"><input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="input"></x-ui.field>
                <x-ui.field label="Date" name="entryDate"><input id="entryDate" type="date" wire:model="entryDate" class="input"></x-ui.field>
                <x-ui.field label="Description" name="description"><input id="description" type="text" wire:model="description" class="input" maxlength="255"></x-ui.field>
                <x-ui.field label="Reference" name="reference"><input id="reference" type="text" wire:model="reference" class="input" maxlength="100"></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($voiding)
        <x-ui.modal title="Void entry" close="closeModals">
            <form wire:submit="void" class="space-y-4">
                <p class="text-sm text-slate-600">Voiding removes {{ $currency }} {{ number_format((float) $voiding->amount, 2) }} from the totals. The record is kept for the audit trail.</p>
                <x-ui.field label="Reason (required)" name="voidReason"><input id="voidReason" type="text" wire:model="voidReason" class="input" maxlength="255" autofocus></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Keep</button><button type="submit" class="btn-danger">Void entry</button></div>
            </form>
        </x-ui.modal>
    @endif
</div>
