<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ ucfirst($plural) }}</h1>
            <p class="text-sm text-slate-500">Manage your {{ $plural }} and their contact details.</p>
        </div>
        @can($permission.'.manage')
            <button type="button" wire:click="create" class="btn-primary">New {{ $singular }}</button>
        @endcan
    </div>

    @if ($notice)
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>
    @endif

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email, phone" aria-label="Search {{ $plural }}" class="input sm:col-span-2">
        <select wire:model.live="view" aria-label="Filter by status" class="input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    <tr wire:key="party-{{ $record->id }}">
                        <td class="px-4 py-3 font-medium">
                            @if ($profileRoute && ! $record->trashed())
                                <a href="{{ route($profileRoute, $record) }}" class="text-indigo-600 hover:underline">{{ $record->name }}</a>
                            @else {{ $record->name }} @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->contact_person ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->phone ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @can($permission.'.manage')
                                @if ($record->trashed())
                                    <button type="button" wire:click="restore({{ $record->id }})" class="text-indigo-600 hover:underline">Restore</button>
                                @else
                                    <button type="button" wire:click="edit({{ $record->id }})" class="text-slate-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="archive({{ $record->id }})" wire:confirm="Archive {{ $record->name }}? Their history is kept." class="ml-2 text-red-600 hover:underline">Archive</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">{{ $search !== '' || $view !== 'active' ? "No {$plural} match these filters." : "No {$plural} yet." }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>

    @if ($showForm)
        <x-ui.modal :title="($form->id ? 'Edit ' : 'New ').$singular" maxWidth="max-w-2xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Name" name="form.name" class="sm:col-span-2"><input id="form.name" type="text" wire:model="form.name" class="input" autofocus></x-ui.field>
                <x-ui.field label="Contact person" name="form.contact_person"><input id="form.contact_person" type="text" wire:model="form.contact_person" class="input"></x-ui.field>
                <x-ui.field label="Tax / VAT number" name="form.tax_number"><input id="form.tax_number" type="text" wire:model="form.tax_number" class="input"></x-ui.field>
                <x-ui.field label="Email" name="form.email"><input id="form.email" type="email" wire:model="form.email" class="input"></x-ui.field>
                <x-ui.field label="Phone" name="form.phone"><input id="form.phone" type="text" wire:model="form.phone" class="input"></x-ui.field>
                <x-ui.field label="Address" name="form.address" class="sm:col-span-2"><textarea id="form.address" rows="2" wire:model="form.address" class="input"></textarea></x-ui.field>
                <x-ui.field label="Notes" name="form.notes" class="sm:col-span-2"><textarea id="form.notes" rows="2" wire:model="form.notes" class="input"></textarea></x-ui.field>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300"> Active</label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
