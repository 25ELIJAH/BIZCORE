<?php

namespace App\Livewire\Purchasing;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Create a purchase order, or edit one that is still a draft. */
#[Layout('layouts.app')]
#[Title('Purchase order')]
class Form extends Component
{
    public ?PurchaseOrder $order = null;

    #[Url(as: 'supplier')]
    public ?int $supplierId = null;

    public string $orderDate = '';

    public string $expectedDate = '';

    public string $taxRate = '0';

    public string $notes = '';

    /** @var list<array{product_id: int, name: string, sku: string, unit: string, quantity: string, unit_cost: string}> */
    public array $lines = [];

    public string $productSearch = '';

    public function mount(?PurchaseOrder $purchaseOrder = null): void
    {
        $this->authorize('purchase_orders.manage');
        $company = app(CurrentCompany::class)->get();

        if ($purchaseOrder?->exists) {
            abort_unless($purchaseOrder->status === PurchaseOrder::DRAFT, 403, 'Only draft purchase orders can be edited.');
            $this->order = $purchaseOrder->load('items');
            $this->supplierId = $purchaseOrder->supplier_id;
            $this->orderDate = $purchaseOrder->order_date->toDateString();
            $this->expectedDate = $purchaseOrder->expected_date?->toDateString() ?? '';
            $this->taxRate = (string) $purchaseOrder->tax_rate;
            $this->notes = (string) $purchaseOrder->notes;
            $units = Product::withTrashed()->whereIn('id', $purchaseOrder->items->pluck('product_id'))->pluck('unit', 'id');
            foreach ($purchaseOrder->items as $item) {
                $this->lines[] = [
                    'product_id' => $item->product_id, 'name' => $item->product_name, 'sku' => $item->sku,
                    'unit' => (string) ($units[$item->product_id] ?? ''), 'quantity' => rtrim(rtrim((string) $item->quantity_ordered, '0'), '.'),
                    'unit_cost' => (string) $item->unit_cost,
                ];
            }

            return;
        }

        $this->orderDate = today()->toDateString();
        $this->taxRate = (string) $company->setting('tax_rate', 0);
        if ($this->supplierId && ! Supplier::where('is_active', true)->whereKey($this->supplierId)->exists()) {
            $this->supplierId = null;
        }
    }

    public function addProduct(int $productId): void
    {
        $this->authorize('purchase_orders.manage');

        $product = Product::where('is_active', true)->findOrFail($productId);

        foreach ($this->lines as $i => $line) {
            if ($line['product_id'] === $product->id) {
                $this->lines[$i]['quantity'] = (string) ((float) $line['quantity'] + 1);
                $this->productSearch = '';

                return;
            }
        }

        $this->lines[] = [
            'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit,
            'quantity' => '1', 'unit_cost' => (string) $product->purchase_price,
        ];
        $this->productSearch = '';
        $this->resetErrorBag('lines');
    }

    public function removeLine(int $index): void
    {
        $this->authorize('purchase_orders.manage');

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(PurchaseOrderService $orders)
    {
        $this->authorize('purchase_orders.manage');
        $companyId = app(CurrentCompany::class)->id();

        $this->validate([
            'supplierId' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'orderDate' => ['required', 'date'],
            'expectedDate' => ['nullable', 'date', 'after_or_equal:orderDate'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ], [
            'supplierId.required' => 'Choose a supplier.',
            'lines.required' => 'Add at least one product to the order.',
            'lines.min' => 'Add at least one product to the order.',
        ], ['lines.*.quantity' => 'quantity', 'lines.*.unit_cost' => 'unit cost', 'supplierId' => 'supplier']);

        try {
            $saved = $orders->saveDraft([
                'supplier_id' => $this->supplierId,
                'order_date' => $this->orderDate,
                'expected_date' => $this->expectedDate ?: null,
                'tax_rate' => $this->taxRate,
                'notes' => $this->notes ?: null,
                'items' => array_map(fn ($l) => Arr::only($l, ['product_id', 'quantity', 'unit_cost']), $this->lines),
            ], $this->order);
        } catch (BusinessRuleException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        session()->flash('status', "Purchase order {$saved->po_number} saved as draft.");

        return $this->redirectRoute('purchase-orders.show', $saved, navigate: true);
    }

    /** Display-only preview; the saved figures are always recalculated by PurchaseOrderService. */
    private function preview(): array
    {
        $subtotal = '0.00';
        foreach ($this->lines as $line) {
            if (is_numeric($line['quantity']) && is_numeric($line['unit_cost'])) {
                $subtotal = Money::add($subtotal, Money::mul($line['quantity'], Money::round($line['unit_cost'])));
            }
        }
        $tax = is_numeric($this->taxRate) ? Money::mul($subtotal, bcdiv($this->taxRate, '100', 6)) : '0.00';

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => Money::add($subtotal, $tax)];
    }

    public function render()
    {
        $term = trim($this->productSearch);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $results = $term === '' ? collect() : Product::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderBy('name')->limit(8)->get();

        return view('livewire.purchasing.form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'results' => $results,
            'totals' => $this->preview(),
            'currency' => app(CurrentCompany::class)->get()->currency,
        ]);
    }
}
