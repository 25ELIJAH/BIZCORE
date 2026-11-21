<?php

namespace App\Livewire\Suppliers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Supplier profile')]
class Show extends Component
{
    use WithPagination;

    public Supplier $supplier;

    public function mount(Supplier $supplier): void
    {
        $this->authorize('suppliers.view');
        $this->supplier = $supplier;
    }

    public function render()
    {
        return view('livewire.suppliers.show', [
            'orders' => $this->supplier->purchaseOrders()->latest('order_date')->latest('id')->paginate(10),
            'balance' => $this->supplier->outstandingBalance(),
            'openOrders' => $this->supplier->purchaseOrders()->whereIn('status', [PurchaseOrder::PENDING, PurchaseOrder::APPROVED, PurchaseOrder::PARTIAL])->count(),
            'currency' => app(CurrentCompany::class)->get()->currency,
        ]);
    }
}
