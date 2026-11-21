<div>
    <a href="{{ route('finance.entries') }}" class="text-sm text-indigo-600 hover:underline">&larr; Income and expenses</a>
    <div class="mb-6 mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Finance categories</h1>
            <p class="text-sm text-slate-500">Categories group your income and expenses. System categories are created automatically for payments.</p>
        </div>
        <button type="button" wire:click="create" class="btn-primary">New category</button>
    </div>

    @if ($notice)<p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Entries</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $c)
                    <tr wire:key="fc-{{ $c->id }}">
                        <td class="px-4 py-3 font-medium">{{ $c->name }} @if ($c->is_system)<span class="badge ml-1 bg-slate-100 text-slate-600">System</span>@endif</td>
                        <td class="px-4 py-3 capitalize">{{ $c->type }}</td>
                        <td class="px-4 py-3 text-right">{{ $c->entries_count }}</td>
                        <td class="px-4 py-3">{{ $c->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right">@unless ($c->is_system)<button type="button" wire:click="edit({{ $c->id }})" class="text-indigo-600 hover:underline">Edit</button>@endunless</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No categories yet. Create one to start recording entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <x-ui.modal :title="$editingId ? 'Edit category' : 'New category'" close="closeModal">
            <form wire:submit="save" class="space-y-4">
                <x-ui.field label="Type" name="type"><select id="type" wire:model="type" class="input"><option value="expense">Expense</option><option value="income">Income</option></select></x-ui.field>
                <x-ui.field label="Name" name="name"><input id="name" type="text" wire:model="name" class="input" maxlength="100" autofocus></x-ui.field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="rounded border-slate-300"> Active (can be used for new entries)</label>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-ui.modal>
    @endif
</div>
