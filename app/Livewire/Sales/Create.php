<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New sale')]
class Create extends Component
{
    #[Url(as: 'customer')]
    public ?int $customerId = null;

    public string $saleDate = '';

    public string $dueDate = '';

    public string $discountType = 'fixed';

    public string $discountValue = '0';

    public string $taxRate = '0';

    public string $notes = '';

    /** @var list<array{product_id: int, name: string, sku: string, unit: string, available: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public string $productSearch = '';

    public string $payNow = '';

    public string $payMethod = 'cash';

    public string $payReference = '';

    /** Makes the optional first payment safe against double submits. */
    public string $paymentKey = '';

    public ?int $completedSaleId = null;

    public function mount(): void
    {
        $this->authorize('sales.create');
        $company = app(CurrentCompany::class)->get();
        $this->saleDate = today()->toDateString();
        $this->taxRate = (string) $company->setting('tax_rate', 0);
        $this->paymentKey = (string) Str::uuid();
        $this->payMethod = array_key_first($company->paymentMethods());

        if ($this->customerId && ! Customer::where('is_active', true)->whereKey($this->customerId)->exists()) {
            $this->customerId = null;
        }
    }

    public function addProduct(int $productId): void
    {
        $this->authorize('sales.create');

        $product = Product::where('is_active', true)->findOrFail($productId);

        foreach ($this->lines as $i => $line) {
            if ($line['product_id'] === $product->id) {
                $this->lines[$i]['quantity'] = (string) ((float) $line['quantity'] + 1);
                $this->productSearch = '';

                return;
            }
        }

        $this->lines[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'unit' => $product->unit,
            'available' => (string) $product->quantity,
            'quantity' => '1',
            'unit_price' => (string) $product->selling_price,
        ];
        $this->productSearch = '';
        $this->resetErrorBag('lines');
    }

    public function removeLine(int $index): void
    {
        $this->authorize('sales.create');

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(SaleService $sales)
    {
        $this->authorize('sales.create');

        if ($this->completedSaleId) {
            return $this->redirectRoute('sales.show', $this->completedSaleId, navigate: true);
        }

        $companyId = app(CurrentCompany::class)->id();
        $this->validate([
            'customerId' => ['nullable', Rule::exists('customers', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'saleDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:saleDate'],
            'discountType' => ['required', Rule::in(['fixed', 'percent'])],
            'discountValue' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'payNow' => ['nullable', 'numeric', 'min:0'],
            'payReference' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Add at least one product to the sale.',
            'lines.min' => 'Add at least one product to the sale.',
        ], ['lines.*.quantity' => 'quantity', 'lines.*.unit_price' => 'unit price']);

        try {
            $sale = $sales->create([
                'customer_id' => $this->customerId,
                'sale_date' => $this->saleDate,
                'due_date' => $this->dueDate ?: null,
                'notes' => $this->notes ?: null,
                'discount_type' => $this->discountType,
                'discount_value' => $this->discountValue,
                'tax_rate' => $this->taxRate,
                'items' => array_map(fn ($l) => Arr::only($l, ['product_id', 'quantity', 'unit_price']), $this->lines),
                // Taking money needs its own permission; ignore the field for users who lack it.
                'payment' => Gate::allows('payments.record') && bccomp($this->payNow ?: '0', '0', 2) > 0
                    ? ['amount' => $this->payNow, 'method' => $this->payMethod, 'reference' => $this->payReference ?: null, 'idempotency_key' => $this->paymentKey]
                    : null,
            ]);
        } catch (InsufficientStockException $e) {
            $this->addError('lines', $e->getMessage());

            return null;
        } catch (BusinessRuleException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        $this->completedSaleId = $sale->id;
        session()->flash('status', "Sale {$sale->invoice->invoice_number} recorded.");

        return $this->redirectRoute('sales.show', $sale, navigate: true);
    }

    /** Display-only preview. The saved figures are always recalculated by SaleService. */
    private function preview(): array
    {
        $subtotal = '0.00';
        foreach ($this->lines as $line) {
            if (is_numeric($line['quantity']) && is_numeric($line['unit_price'])) {
                $subtotal = Money::add($subtotal, Money::mul($line['quantity'], Money::round($line['unit_price'])));
            }
        }

        $value = is_numeric($this->discountValue) ? Money::round($this->discountValue) : '0.00';
        $discount = $this->discountType === 'percent' ? Money::mul($subtotal, bcdiv($value, '100', 6)) : $value;
        $discount = Money::compare($discount, $subtotal) > 0 ? $subtotal : $discount;
        $taxable = Money::sub($subtotal, $discount);
        $tax = is_numeric($this->taxRate) ? Money::mul($taxable, bcdiv($this->taxRate, '100', 6)) : '0.00';

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => Money::add($taxable, $tax)];
    }

    public function render()
    {
        $term = trim($this->productSearch);
        $results = $term === '' ? collect() : Product::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('sku', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit(8)->get();

        $company = app(CurrentCompany::class)->get();

        return view('livewire.sales.create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'results' => $results,
            'totals' => $this->preview(),
            'paymentMethods' => $company->paymentMethods(),
            'currency' => $company->currency,
            'allowNegative' => (bool) $company->setting('allow_negative_stock', false),
        ]);
    }
}
