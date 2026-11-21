<?php

namespace App\Livewire\Purchasing;

use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Purchase order')]
class Show extends Component
{
    public PurchaseOrder $order;

    public string $notice = '';

    /** reject | cancel | null: which reason modal is open */
    public ?string $reasonModal = null;

    public string $reason = '';

    // Receive goods
    public bool $showReceive = false;

    /** @var array<int, string> purchase_order_item_id => quantity being received now */
    public array $receiveQty = [];

    public string $receiveDate = '';

    public string $receiveNotes = '';

    // Supplier payment
    public bool $showPayment = false;

    public string $payAmount = '';

    public string $payMethod = 'cash';

    public string $payDate = '';

    public string $payReference = '';

    public string $paymentKey = '';

    public ?int $voidPaymentId = null;

    public string $voidReason = '';

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->authorize('purchase_orders.view');
        $this->order = $purchaseOrder;
    }

    // ---- workflow -------------------------------------------------------

    public function submit(PurchaseOrderService $orders): void
    {
        $this->authorize('purchase_orders.manage');
        $this->run(fn () => $orders->submit($this->order), 'Submitted for approval.');
    }

    public function approve(PurchaseOrderService $orders): void
    {
        $this->authorize('purchase_orders.approve');
        $this->run(fn () => $orders->approve($this->order), 'Purchase order approved.');
    }

    public function openReason(string $kind): void
    {
        abort_unless(in_array($kind, ['reject', 'cancel'], true), 404);
        $this->authorize($kind === 'reject' ? 'purchase_orders.approve' : 'purchase_orders.manage');
        $this->resetValidation();
        $this->reason = '';
        $this->reasonModal = $kind;
    }

    public function submitReason(PurchaseOrderService $orders): void
    {
        $kind = $this->reasonModal;
        abort_unless(in_array($kind, ['reject', 'cancel'], true), 404);
        $this->authorize($kind === 'reject' ? 'purchase_orders.approve' : 'purchase_orders.manage');
        $this->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $kind === 'reject' ? $orders->reject($this->order, $this->reason) : $orders->cancel($this->order, $this->reason);
        } catch (BusinessRuleException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->notice = $kind === 'reject' ? 'Returned to draft for changes.' : 'Purchase order cancelled.';
        $this->closeModals();
    }

    // ---- receiving ------------------------------------------------------

    public function openReceive(): void
    {
        $this->authorize('goods.receive');
        abort_unless($this->order->canReceive(), 403);
        $this->resetValidation();
        $this->receiveQty = [];
        foreach ($this->order->items as $item) {
            $this->receiveQty[$item->id] = '';
        }
        $this->receiveDate = today()->toDateString();
        $this->receiveNotes = '';
        $this->showReceive = true;
    }

    public function receive(GoodsReceiptService $receipts): void
    {
        $this->authorize('goods.receive');
        $this->validate([
            'receiveQty.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999.999'],
            'receiveDate' => ['required', 'date', 'before_or_equal:today'],
            'receiveNotes' => ['nullable', 'string', 'max:2000'],
        ], [], ['receiveDate' => 'received date']);

        $lines = [];
        foreach ($this->receiveQty as $itemId => $qty) {
            $lines[] = ['purchase_order_item_id' => (int) $itemId, 'quantity' => $qty === '' ? '0' : $qty];
        }

        try {
            $receipt = $receipts->receive($this->order, $lines, $this->receiveDate, $this->receiveNotes ?: null);
        } catch (BusinessRuleException $e) {
            $this->addError('receiveQty', $e->getMessage());

            return;
        }

        $this->notice = "Goods received ({$receipt->receipt_number}). Stock updated.";
        $this->closeModals();
    }

    // ---- supplier payments ---------------------------------------------

    public function openPayment(): void
    {
        $this->authorize('supplier_payments.record');
        abort_unless($this->order->isPayable(), 403);
        $this->resetValidation();
        $this->payAmount = $this->order->balance();
        $this->payMethod = array_key_first(app(CurrentCompany::class)->get()->paymentMethods());
        $this->payDate = today()->toDateString();
        $this->payReference = '';
        $this->paymentKey = (string) Str::uuid();
        $this->showPayment = true;
    }

    public function recordPayment(SupplierPaymentService $payments): void
    {
        $this->authorize('supplier_payments.record');
        $this->validate([
            'payAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'payMethod' => ['required', Rule::in(array_keys(app(CurrentCompany::class)->get()->paymentMethods()))],
            'payDate' => ['required', 'date', 'before_or_equal:today'],
            'payReference' => ['nullable', 'string', 'max:255'],
        ], [], ['payAmount' => 'amount', 'payMethod' => 'method', 'payDate' => 'date']);

        try {
            $payments->record($this->order, $this->payAmount, $this->payMethod, $this->payDate, $this->payReference ?: null, $this->paymentKey);
        } catch (BusinessRuleException $e) {
            $this->addError('payAmount', $e->getMessage());

            return;
        }

        $this->notice = 'Supplier payment recorded.';
        $this->closeModals();
    }

    public function openVoid(int $paymentId): void
    {
        $this->authorize('supplier_payments.record');
        $this->order->payments()->active()->findOrFail($paymentId);
        $this->resetValidation();
        $this->voidReason = '';
        $this->voidPaymentId = $paymentId;
    }

    public function voidPayment(SupplierPaymentService $payments): void
    {
        $this->authorize('supplier_payments.record');
        $this->validate(['voidReason' => ['required', 'string', 'max:255']], [], ['voidReason' => 'reason']);

        try {
            $payments->void($this->order->payments()->findOrFail($this->voidPaymentId), $this->voidReason);
        } catch (BusinessRuleException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->notice = 'Payment voided.';
        $this->closeModals();
    }

    public function closeModals(): void
    {
        $this->reasonModal = null;
        $this->showReceive = $this->showPayment = false;
        $this->voidPaymentId = null;
        $this->order->refresh();
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
        } catch (BusinessRuleException $e) {
            $this->addError('action', $e->getMessage());

            return;
        }
        $this->notice = $success;
    }

    public function render()
    {
        $this->order->load(['items', 'supplier', 'creator', 'approver', 'receipts.items', 'receipts.user', 'payments.user']);
        $company = app(CurrentCompany::class)->get();

        return view('livewire.purchasing.show', [
            'paymentMethods' => $company->paymentMethods(),
            'currency' => $company->currency,
            'voidPayment' => $this->voidPaymentId ? SupplierPayment::find($this->voidPaymentId) : null,
        ]);
    }
}
