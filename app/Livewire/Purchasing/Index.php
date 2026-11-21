<?php

namespace App\Livewire\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Purchase orders')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public ?int $supplier = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('purchase_orders.view');
    }

    public function updating(string $property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->when(array_key_exists($this->status, PurchaseOrder::STATUS_LABELS), fn ($q) => $q->where('status', $this->status))
            ->when($this->supplier, fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->from !== '', fn ($q) => $q->whereDate('order_date', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('order_date', '<=', $this->to))
            ->when($this->search !== '', function ($q) {
                $term = '%'.addcslashes($this->search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('po_number', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->withTrashed()->where('name', 'like', $term)));
            })
            ->latest('order_date')->latest('id')
            ->paginate(15);

        return view('livewire.purchasing.index', [
            'orders' => $orders,
            'suppliers' => Supplier::withTrashed()->orderBy('name')->get(['id', 'name']),
            'currency' => app(CurrentCompany::class)->get()->currency,
        ]);
    }
}
