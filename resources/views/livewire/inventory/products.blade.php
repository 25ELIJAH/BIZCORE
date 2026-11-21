<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Products</h1>
            <p class="text-sm text-slate-500">Catalogue and current stock levels.</p>
        </div>
        @can('inventory.manage')
            <button type="button" wire:click="create" class="btn-primary">New product</button>
        @endcan
    </div>

    @if ($notice)
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>
    @endif

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or SKU" aria-label="Search products" class="input sm:col-span-2">
        <select wire:model.live="categoryId" aria-label="Filter by category" class="input">
            <option value="">All categories</option>
            @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
        </select>
        <select wire:model.live="view" aria-label="Filter by status" class="input">
            <option value="active">Active products</option>
            <option value="low">Low stock</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">In stock</th>
                    <th class="px-4 py-3 text-right">Min</th>
                    <th class="px-4 py-3 text-right">Price ({{ $currency }})</th>
                    <th class="px-4 py-3 text-right">Stock value</th>
                    <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    <tr wire:key="product-{{ $product->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $product->name }}</div>
                            <div class="text-xs text-slate-500">{{ $product->sku }}@unless($product->is_active) · inactive @endunless</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            {{ rtrim(rtrim($product->quantity, '0'), '.') ?: '0' }} {{ $product->unit }}
                            @if ($product->isLowStock())<span class="badge ml-1 bg-amber-100 text-amber-800">Low</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim($product->min_stock, '0'), '.') ?: '0' }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $product->selling_price, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $product->stockValue(), 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($product->trashed())
                                @can('inventory.manage')
                                    <button type="button" wire:click="restore({{ $product->id }})" class="text-indigo-600 hover:underline">Restore</button>
                                @endcan
                            @else
                                @can('inventory.adjust')
                                    <button type="button" wire:click="openStock({{ $product->id }}, 'add')" class="text-indigo-600 hover:underline">Add</button>
                                    <button type="button" wire:click="openStock({{ $product->id }}, 'deduct')" class="ml-2 text-indigo-600 hover:underline">Deduct</button>
                                    <button type="button" wire:click="openStock({{ $product->id }}, 'count')" class="ml-2 text-indigo-600 hover:underline">Count</button>
                                @endcan
                                <a href="{{ route('inventory.movements', ['product' => $product->id]) }}" class="ml-2 text-slate-600 hover:underline">History</a>
                                @can('inventory.manage')
                                    <button type="button" wire:click="edit({{ $product->id }})" class="ml-2 text-slate-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="archive({{ $product->id }})"
                                            wire:confirm="Archive {{ $product->name }}? Its stock history is kept." class="ml-2 text-red-600 hover:underline">Archive</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">
                        {{ $search !== '' || $categoryId || $view !== 'active' ? 'No products match these filters.' : 'No products yet. Create your first product to start tracking stock.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>

    @if ($showForm)
        <x-ui.modal :title="$form->id ? 'Edit product' : 'New product'" maxWidth="max-w-2xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="SKU" name="form.sku"><input id="form.sku" type="text" wire:model="form.sku" class="input"></x-ui.field>
                <x-ui.field label="Name" name="form.name"><input id="form.name" type="text" wire:model="form.name" class="input"></x-ui.field>
                <x-ui.field label="Category" name="form.product_category_id">
                    <select id="form.product_category_id" wire:model="form.product_category_id" class="input">
                        <option value="">None</option>
                        @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Unit" name="form.unit"><input id="form.unit" type="text" wire:model="form.unit" class="input" placeholder="pcs, kg, box"></x-ui.field>
                <x-ui.field label="Purchase price" name="form.purchase_price"><input id="form.purchase_price" type="number" step="0.01" min="0" wire:model="form.purchase_price" class="input"></x-ui.field>
                <x-ui.field label="Selling price" name="form.selling_price"><input id="form.selling_price" type="number" step="0.01" min="0" wire:model="form.selling_price" class="input"></x-ui.field>
                <x-ui.field label="Minimum stock level" name="form.min_stock" hint="Flagged as low stock at or below this quantity."><input id="form.min_stock" type="number" step="0.001" min="0" wire:model="form.min_stock" class="input"></x-ui.field>
                @unless ($form->id)
                    <x-ui.field label="Opening stock" name="form.opening_quantity" hint="Recorded as an opening stock movement."><input id="form.opening_quantity" type="number" step="0.001" min="0" wire:model="form.opening_quantity" class="input"></x-ui.field>
                @endunless
                <x-ui.field label="Description" name="form.description" class="sm:col-span-2"><textarea id="form.description" rows="2" wire:model="form.description" class="input"></textarea></x-ui.field>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300"> Active (available for sale)</label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save product</button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showStock && $stockProduct)
        <x-ui.modal :title="match($stockMode) { 'add' => 'Add stock', 'deduct' => 'Deduct stock', 'count' => 'Stock count adjustment' }.' — '.$stockProduct->name">
            <p class="mb-4 text-sm text-slate-600">Currently in stock: <strong>{{ rtrim(rtrim($stockProduct->quantity, '0'), '.') ?: '0' }} {{ $stockProduct->unit }}</strong></p>
            <form wire:submit="applyStock" class="space-y-4">
                <x-ui.field :label="$stockMode === 'count' ? 'Counted quantity' : 'Quantity'" name="stockQuantity">
                    <input id="stockQuantity" type="number" step="0.001" min="0" wire:model="stockQuantity" class="input" autofocus>
                </x-ui.field>
                @if ($stockMode === 'add')
                    <x-ui.field label="Unit cost (optional)" name="stockUnitCost" hint="Updates the weighted-average cost used for valuation.">
                        <input id="stockUnitCost" type="number" step="0.01" min="0" wire:model="stockUnitCost" class="input">
                    </x-ui.field>
                @endif
                <x-ui.field :label="$stockMode === 'count' ? 'Reason (required, audited)' : 'Reason (optional)'" name="stockReason">
                    <input id="stockReason" type="text" wire:model="stockReason" class="input" maxlength="255">
                </x-ui.field>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Apply</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
