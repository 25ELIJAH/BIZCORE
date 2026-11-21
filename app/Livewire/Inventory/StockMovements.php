<?php

namespace App\Livewire\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stock movements')]
class StockMovements extends Component
{
    use WithPagination;

    #[Url]
    public ?int $product = null;

    #[Url]
    public string $type = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('inventory.view');
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $movements = StockMovement::query()
            ->with(['product', 'user'])
            ->when($this->product, fn ($q) => $q->where('product_id', $this->product))
            ->when(StockMovementType::tryFrom($this->type), fn ($q, $type) => $q->where('type', $type))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('created_at')->latest('id')
            ->paginate(20);

        return view('livewire.inventory.stock-movements', [
            'movements' => $movements,
            'products' => Product::withTrashed()->orderBy('name')->get(['id', 'name', 'sku']),
            'types' => StockMovementType::cases(),
        ]);
    }
}
