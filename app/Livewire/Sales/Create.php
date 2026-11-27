<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\MpesaTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Services\MpesaService;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
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
    /** The only ways to take payment on this screen, in the order they are listed. */
    private const PAY_METHODS = ['cash', 'mpesa', 'bank_transfer'];

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

    // ---- Payment taken while completing the sale ('' = no payment now, the invoice stays open) ----

    public string $payMethod = '';

    public string $payNow = '';

    /** Transaction code: the bank reference, or the M-Pesa receipt when prompts are not set up. */
    public string $payReference = '';

    public string $payerName = '';

    public string $payBank = '';

    public string $payTime = '';

    /** While true the amount follows the sale total as items change; typing an amount stops that. */
    public bool $payFollowsTotal = true;

    /** Makes the optional first payment safe against double submits. */
    public string $paymentKey = '';

    // ---- M-Pesa prompt dialog ----

    public bool $showMpesa = false;

    public bool $mpesaManual = false;

    public string $mpesaPhone = '';

    public string $mpesaAmount = '';

    public ?int $mpesaTxId = null;

    public ?int $completedSaleId = null;

    public function mount(): void
    {
        $this->authorize('sales.create');
        $company = app(CurrentCompany::class)->get();
        $this->saleDate = today()->toDateString();
        $this->taxRate = (string) $company->setting('tax_rate', 0);
        $this->paymentKey = (string) Str::uuid();

        if ($this->customerId && ! Customer::where('is_active', true)->whereKey($this->customerId)->exists()) {
            $this->customerId = null;
        }
    }

    // ---- the payment dropdown ----------------------------------------------------------------------

    /** @return array<string, string> slug => label */
    private function payMethods(): array
    {
        $all = app(CurrentCompany::class)->get()->paymentMethods();
        $out = [];
        foreach (self::PAY_METHODS as $slug) {
            if (isset($all[$slug])) {
                $out[$slug] = $all[$slug];
            }
        }

        return $out;
    }

    private function mpesaReady(): bool
    {
        return app(MpesaService::class)->isConfigured(app(CurrentCompany::class)->get());
    }

    public function updatedPayMethod(string $value): void
    {
        $this->resetValidation(['payMethod', 'payNow', 'payReference', 'mpesaPhone', 'lines']);

        if ($value === '' || ! Gate::allows('payments.record') || ! array_key_exists($value, $this->payMethods())) {
            $this->payMethod = '';
            $this->payNow = '';

            return;
        }

        if ($this->payNow === '') {
            $this->payFollowsTotal = true;
        }
        $this->payTime = now()->format('Y-m-d\TH:i');
        $this->syncPay();

        $this->mpesaManual = false;
        if ($value === 'mpesa') {
            $this->openMpesa();
        }
    }

    /** M-Pesa not connected yet (or the cashier already has the code): record it by hand instead of sending a prompt. */
    public function useManualMpesa(): void
    {
        $this->mpesaManual = true;
        $this->showMpesa = false;
        $this->payFollowsTotal = true;
        $this->syncPay();
    }

    public function updatedPayNow(): void
    {
        $this->payFollowsTotal = false;
    }

    public function updatedLines(): void
    {
        $this->syncPay();
    }

    public function updatedDiscountValue(): void
    {
        $this->syncPay();
    }

    public function updatedDiscountType(): void
    {
        $this->syncPay();
    }

    public function updatedTaxRate(): void
    {
        $this->syncPay();
    }

    private function syncPay(): void
    {
        if ($this->payMethod === '' || ! $this->payFollowsTotal) {
            return;
        }

        $this->payNow = (string) $this->preview()['total'];
    }

    // ---- the M-Pesa dialog -------------------------------------------------------------------------

    private function openMpesa(): void
    {
        $this->authorize('payments.record');

        if (! $this->lines || Money::compare($this->preview()['total'], '1.00') < 0) {
            $this->addError('payMethod', 'Add the items first, then choose M-Pesa.');
            $this->payMethod = '';
            $this->payNow = '';

            return;
        }

        $this->resetValidation();
        $this->mpesaAmount = (string) floor((float) $this->preview()['total']);
        $this->mpesaPhone = $this->mpesaPhone !== '' ? $this->mpesaPhone : (string) ($this->customerId ? Customer::whereKey($this->customerId)->value('phone') : '');
        $this->mpesaTxId = null;
        $this->showMpesa = true;
    }

    /** Save the sale (once) and send the prompt for what the customer is paying now. */
    public function sendMpesa(SaleService $sales, MpesaService $mpesa): void
    {
        $this->authorize('sales.create');
        $this->authorize('payments.record');

        $this->validate([
            'mpesaPhone' => ['required', 'string', 'max:20'],
            'mpesaAmount' => ['required', 'numeric', 'min:1'],
        ], [], ['mpesaPhone' => 'phone number', 'mpesaAmount' => 'amount']);

        if (MpesaService::normalizePhone($this->mpesaPhone) === null) {
            $this->addError('mpesaPhone', 'Enter a valid Safaricom number, for example 0712 345 678.');

            return;
        }
        if (floor((float) $this->mpesaAmount) != (float) $this->mpesaAmount) {
            $this->addError('mpesaAmount', 'M-Pesa takes whole shillings: no cents.');

            return;
        }

        if (! $this->mpesaReady()) {
            $this->addError('mpesaPhone', 'M-Pesa is not connected yet, so no prompt can be sent. Ask an administrator to add the Daraja details on the Company page.');

            return;
        }

        $sale = $this->completedSaleId ? Sale::find($this->completedSaleId) : $this->createSale($sales, null);
        if (! $sale) {
            $this->showMpesa = false; // the sale form has an error to fix first; it is shown on the page

            return;
        }

        try {
            $tx = $mpesa->stkPush($sale, $this->mpesaPhone, $this->mpesaAmount, Auth::user());
        } catch (BusinessRuleException $e) {
            $this->addError('mpesaPhone', $e->getMessage()); // the sale is saved; the cashier can fix this and try again

            return;
        }

        $this->mpesaTxId = $tx->id;
    }

    /** Polled while the customer is looking at their phone. */
    public function checkMpesa(MpesaService $mpesa): void
    {
        $tx = $this->mpesaTransaction();
        if ($tx && $tx->isPending()) {
            $mpesa->checkStatus($tx);
        }
    }

    public function retryMpesa(): void
    {
        $this->mpesaTxId = null;
    }

    /** Close the dialog: before anything was sent this just backs out; afterwards the sale exists, so go to it. */
    public function closeMpesa()
    {
        if ($this->completedSaleId) {
            return $this->goToSale();
        }

        $this->showMpesa = false;
        $this->payMethod = '';
        $this->payNow = '';
        $this->resetValidation();

        return null;
    }

    public function goToSale()
    {
        $sale = Sale::with('invoice')->findOrFail($this->completedSaleId);
        session()->flash('status', "Sale {$sale->invoice->invoice_number} recorded.");

        return $this->redirectRoute('sales.show', $sale, navigate: true);
    }

    private function mpesaTransaction(): ?MpesaTransaction
    {
        return $this->mpesaTxId && $this->completedSaleId
            ? MpesaTransaction::where('company_id', app(CurrentCompany::class)->id())->where('sale_id', $this->completedSaleId)->find($this->mpesaTxId)
            : null;
    }

    // ---- items -------------------------------------------------------------------------------------

    public function addProduct(int $productId): void
    {
        $this->authorize('sales.create');

        $product = Product::where('is_active', true)->findOrFail($productId);

        foreach ($this->lines as $i => $line) {
            if ($line['product_id'] === $product->id) {
                $this->lines[$i]['quantity'] = (string) ((float) $line['quantity'] + 1);
                $this->productSearch = '';
                $this->syncPay();

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
            'tax_type' => (string) $product->tax_type,
        ];
        $this->productSearch = '';
        $this->resetErrorBag('lines');
        $this->syncPay();
    }

    public function removeLine(int $index): void
    {
        $this->authorize('sales.create');

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->syncPay();
    }

    // ---- saving ------------------------------------------------------------------------------------

    public function save(SaleService $sales)
    {
        $this->authorize('sales.create');

        if ($this->completedSaleId) {
            return $this->goToSale();
        }

        $takingPayment = Gate::allows('payments.record') && $this->payMethod !== '';

        // M-Pesa with prompts set up is done in its own dialog.
        if ($takingPayment && $this->payMethod === 'mpesa' && ! $this->mpesaManual) {
            $this->openMpesa();

            return null;
        }

        $payment = null;
        if ($takingPayment) {
            if (! is_numeric($this->payNow) || Money::compare(Money::round($this->payNow), '0.00') <= 0) {
                $this->addError('payNow', 'Enter the amount received.');

                return null;
            }
            if ($this->payMethod !== 'cash' && trim($this->payReference) === '') {
                $this->addError('payReference', $this->payMethod === 'mpesa' ? 'Enter the M-Pesa code from the message.' : 'Enter the transaction code from the bank.');

                return null;
            }
            $payment = ['amount' => $this->payNow, 'method' => $this->payMethod, 'reference' => $this->payReference ?: null, 'idempotency_key' => $this->paymentKey];
        }

        $sale = $this->createSale($sales, $payment);

        return $sale ? $this->goToSale() : null;
    }

    /** Validate the form and save the sale (and, when given, its first payment). Returns null after showing the problem. */
    private function createSale(SaleService $sales, ?array $payment): ?Sale
    {
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
            'payerName' => ['nullable', 'string', 'max:255'],
            'payBank' => ['nullable', 'string', 'max:255'],
            'payTime' => ['nullable', 'date'],
            'payReference' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Add at least one product to the sale.',
            'lines.min' => 'Add at least one product to the sale.',
        ], ['lines.*.quantity' => 'quantity', 'lines.*.unit_price' => 'unit price', 'payTime' => 'payment time']);

        if ($payment) {
            // Who paid: the name typed for a bank payment, otherwise the customer on the sale.
            $customerName = $this->customerId ? Customer::whereKey($this->customerId)->value('name') : null;
            $payment['details'] = [
                'payer_name' => $this->payMethod === 'bank_transfer' && trim($this->payerName) !== '' ? trim($this->payerName) : $customerName,
                'bank_name' => $this->payMethod === 'bank_transfer' ? trim($this->payBank) : null,
                'transacted_at' => $this->payMethod === 'cash' || $this->payTime === '' ? now() : $this->payTime,
            ];
        }

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
                'payment' => $payment,
            ]);
        } catch (InsufficientStockException $e) {
            $this->addError('lines', $e->getMessage());

            return null;
        } catch (BusinessRuleException $e) {
            $this->addError('form', $e->getMessage());

            return null;
        }

        $this->completedSaleId = $sale->id;

        return $sale;
    }

    /** Display-only preview. The saved figures are always recalculated by SaleService. */
    private function preview(): array
    {
        $subtotal = '0.00';
        $standard = '0.00'; // the part of the subtotal that carries VAT
        foreach ($this->lines as $line) {
            if (is_numeric($line['quantity']) && is_numeric($line['unit_price'])) {
                $amount = Money::mul($line['quantity'], Money::round($line['unit_price']));
                $subtotal = Money::add($subtotal, $amount);
                if (($line['tax_type'] ?? 'standard') === 'standard') {
                    $standard = Money::add($standard, $amount);
                }
            }
        }

        $value = is_numeric($this->discountValue) ? Money::round($this->discountValue) : '0.00';
        $discount = $this->discountType === 'percent' ? Money::mul($subtotal, bcdiv($value, '100', 6)) : $value;
        $discount = Money::compare($discount, $subtotal) > 0 ? $subtotal : $discount;
        $taxable = Money::sub($subtotal, $discount);
        $vatable = bccomp($subtotal, '0', 2) > 0 ? Money::sub($standard, Money::mul($discount, bcdiv($standard, $subtotal, 8))) : '0.00';
        $tax = is_numeric($this->taxRate) ? Money::mul($vatable, bcdiv($this->taxRate, '100', 6)) : '0.00';

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => Money::add($taxable, $tax)];
    }

    public function render()
    {
        $term = trim($this->productSearch);
        $results = $term === '' ? collect() : Product::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('sku', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit(8)->get();

        $company = app(CurrentCompany::class)->get();
        $customer = $this->customerId ? Customer::whereKey($this->customerId)->first(['id', 'name']) : null;

        return view('livewire.sales.create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'results' => $results,
            'totals' => $this->preview(),
            'payMethods' => $this->payMethods(),
            'mpesaReady' => $this->mpesaReady(),
            'mpesaTx' => $this->showMpesa ? $this->mpesaTransaction() : null,
            'customerName' => $customer?->name ?? 'Walk-in customer',
            'currency' => $company->currency,
            'allowNegative' => (bool) $company->setting('allow_negative_stock', false),
        ]);
    }
}
