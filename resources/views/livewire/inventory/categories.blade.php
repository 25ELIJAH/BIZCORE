<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Product categories</h1>
            <p class="text-sm text-slate-500">Group products for browsing and reporting.</p>
        </div>
        @can('inventory.manage')
            <button type="button" wire:click="create" class="btn-primary">New category</button>
        @endcan
    </div>

    @if ($notice)
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Description</th><th class="px-4 py-3 text-right">Products</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $category->description ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $category->products_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @can('inventory.manage')
                                <button type="button" wire:click="edit({{ $category->id }})" class="text-slate-600 hover:underline">Edit</button>
                                <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Delete the category “{{ $category->name }}”?" class="ml-2 text-red-600 hover:underline">Delete</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <x-ui.modal :title="$editingId ? 'Edit category' : 'New category'">
            <form wire:submit="save" class="space-y-4">
                <x-ui.field label="Name" name="name"><input id="name" type="text" wire:model="name" class="input" autofocus></x-ui.field>
                <x-ui.field label="Description" name="description"><input id="description" type="text" wire:model="description" class="input"></x-ui.field>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
