<?php

namespace App\Livewire\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Livewire\Forms\ProductForm;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use App\Services\StockService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Products')]
class Products extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public ?int $categoryId = null;

    /** active | archived | low */
    #[Url]
    public string $view = 'active';

    public ProductForm $form;

    public bool $showForm = false;

    // Stock operation modal: add | deduct | count
    public bool $showStock = false;

    public ?int $stockProductId = null;

    public string $stockMode = 'add';

    public string $stockQuantity = '';

    public string $stockUnitCost = '';

    public string $stockReason = '';

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'categoryId', 'view'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('inventory.manage');
        $this->form->reset();
        $this->form->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('inventory.manage');
        $this->form->resetValidation();
        $this->form->fillFrom(Product::findOrFail($id));
        $this->showForm = true;
    }

    public function save(ProductService $products): void
    {
        $this->authorize('inventory.manage');
        $this->form->validate();

        if ($this->form->id) {
            $products->update(Product::findOrFail($this->form->id), $this->form->attributes());
            $this->notice = 'Product updated.';
        } else {
            $products->create($this->form->attributes(), $this->form->opening_quantity ?: 0);
            $this->notice = 'Product created.';
        }

        $this->closeModal();
    }

    public function archive(int $id, ProductService $products): void
    {
        $this->authorize('inventory.manage');
        $products->archive(Product::findOrFail($id));
        $this->notice = 'Product archived. Its stock history is kept.';
    }

    public function restore(int $id, ProductService $products): void
    {
        $this->authorize('inventory.manage');
        $products->restore(Product::onlyTrashed()->findOrFail($id));
        $this->notice = 'Product restored.';
    }

    public function openStock(int $id, string $mode): void
    {
        $this->authorize('inventory.adjust');
        abort_unless(in_array($mode, ['add', 'deduct', 'count'], true), 422);
        Product::findOrFail($id);

        $this->reset('stockQuantity', 'stockUnitCost', 'stockReason');
        $this->resetValidation();
        $this->stockProductId = $id;
        $this->stockMode = $mode;
        $this->showStock = true;
    }

    public function applyStock(StockService $stock): void
    {
        $this->authorize('inventory.adjust');

        $this->validate([
            'stockQuantity' => ['required', 'numeric', $this->stockMode === 'count' ? 'min:0' : 'gt:0', 'max:999999999999.999'],
            'stockUnitCost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'stockReason' => [$this->stockMode === 'count' ? 'required' : 'nullable', 'string', 'max:255'],
        ], [], ['stockQuantity' => $this->stockMode === 'count' ? 'counted quantity' : 'quantity', 'stockReason' => 'reason']);

        $product = Product::findOrFail($this->stockProductId);

        try {
            match ($this->stockMode) {
                'add' => $stock->add($product, $this->stockQuantity, StockMovementType::Addition, $this->stockUnitCost !== '' ? $this->stockUnitCost : null, reason: $this->stockReason ?: null),
                'deduct' => $stock->deduct($product, $this->stockQuantity, StockMovementType::Deduction, reason: $this->stockReason ?: null),
                'count' => $stock->adjustTo($product, $this->stockQuantity, $this->stockReason),
            };
        } catch (InsufficientStockException $e) {
            $this->addError('stockQuantity', "Only {$e->available} {$product->unit} in stock.");

            return;
        }

        $this->notice = 'Stock updated.';
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->showStock = false;
    }

    public function render()
    {
        $query = Product::query()->with('category');

        if ($this->view === 'archived') {
            $query->onlyTrashed();
        } elseif ($this->view === 'low') {
            $query->lowStock();
        }

        $products = $query
            ->when($this->categoryId, fn ($q) => $q->where('product_category_id', $this->categoryId))
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.inventory.products', [
            'products' => $products,
            'categories' => ProductCategory::orderBy('name')->get(['id', 'name']),
            'stockProduct' => $this->stockProductId ? Product::find($this->stockProductId) : null,
            'currency' => app(\App\Support\Tenancy\CurrentCompany::class)->get()->currency,
        ]);
    }
}
