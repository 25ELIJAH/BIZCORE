<?php

namespace App\Livewire\Sales;

use App\Exceptions\BusinessRuleException;
use App\Mail\InvoiceMail;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sale')]
class Show extends Component
{
    public Sale $sale;

    public string $notice = '';

    // Record payment modal
    public bool $showPayment = false;

    public string $payAmount = '';

    public string $payMethod = 'cash';

    public string $payDate = '';

    public string $payReference = '';

    public string $paymentKey = '';

    // Void payment modal
    public ?int $voidPaymentId = null;

    public string $voidReason = '';

    // Cancel sale modal
    public bool $showCancel = false;

    public string $cancelReason = '';

    // Edit details modal
    public bool $showEdit = false;

    public string $editDueDate = '';

    public string $editNotes = '';

    public function mount(Sale $sale): void
    {
        $this->authorize('sales.view');
        $this->sale = $sale;
    }

    public function openPayment(): void
    {
        $this->authorize('payments.record');
        $this->resetValidation();
        $this->payAmount = $this->sale->balance();
        $this->payMethod = array_key_first(app(CurrentCompany::class)->get()->paymentMethods());
        $this->payDate = today()->toDateString();
        $this->payReference = '';
        $this->paymentKey = (string) Str::uuid(); // one token per modal opening: re-clicking Save cannot double-record
        $this->showPayment = true;
    }

    public function recordPayment(PaymentService $payments): void
    {
        $this->authorize('payments.record');
        $this->validate([
            'payAmount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'payMethod' => ['required', Rule::in(array_keys(app(CurrentCompany::class)->get()->paymentMethods()))],
            'payDate' => ['required', 'date', 'before_or_equal:today'],
            'payReference' => ['nullable', 'string', 'max:255'],
        ], [], ['payAmount' => 'amount', 'payMethod' => 'method', 'payDate' => 'date']);

        try {
            $payments->record($this->sale, $this->payAmount, $this->payMethod, $this->payDate, $this->payReference ?: null, $this->paymentKey);
        } catch (BusinessRuleException $e) {
            $this->addError('payAmount', $e->getMessage());

            return;
        }

        $this->sale->refresh();
        $this->notice = 'Payment recorded.';
        $this->closeModals();
    }

    public function openVoid(int $paymentId): void
    {
        $this->authorize('payments.record');
        $this->sale->payments()->active()->findOrFail($paymentId);
        $this->resetValidation();
        $this->voidReason = '';
        $this->voidPaymentId = $paymentId;
    }

    public function voidPayment(PaymentService $payments): void
    {
        $this->authorize('payments.record');
        $this->validate(['voidReason' => ['required', 'string', 'max:255']], [], ['voidReason' => 'reason']);

        try {
            $payments->void($this->sale->payments()->findOrFail($this->voidPaymentId), $this->voidReason);
        } catch (BusinessRuleException $e) {
            $this->addError('voidReason', $e->getMessage());

            return;
        }

        $this->sale->refresh();
        $this->notice = 'Payment voided.';
        $this->closeModals();
    }

    public function openCancel(): void
    {
        $this->authorize('sales.cancel');
        $this->resetValidation();
        $this->cancelReason = '';
        $this->showCancel = true;
    }

    public function cancelSale(SaleService $sales): void
    {
        $this->authorize('sales.cancel');
        $this->validate(['cancelReason' => ['required', 'string', 'max:255']], [], ['cancelReason' => 'reason']);

        try {
            $sales->cancel($this->sale, $this->cancelReason);
        } catch (BusinessRuleException $e) {
            $this->addError('cancelReason', $e->getMessage());

            return;
        }

        $this->sale->refresh();
        $this->notice = 'Sale cancelled and stock restored.';
        $this->closeModals();
    }

    public function openEdit(): void
    {
        $this->authorize('sales.edit');
        abort_if($this->sale->isCancelled(), 403);
        $this->resetValidation();
        $this->editDueDate = $this->sale->due_date->toDateString();
        $this->editNotes = (string) $this->sale->notes;
        $this->showEdit = true;
    }

    public function saveEdit(SaleService $sales): void
    {
        $this->authorize('sales.edit');
        $this->validate(['editDueDate' => ['required', 'date'], 'editNotes' => ['nullable', 'string', 'max:2000']], [], ['editDueDate' => 'due date']);

        try {
            $sales->updateDetails($this->sale, $this->editDueDate, $this->editNotes ?: null);
        } catch (BusinessRuleException $e) {
            $this->addError('editDueDate', $e->getMessage());

            return;
        }

        $this->sale->refresh();
        $this->notice = 'Sale details updated.';
        $this->closeModals();
    }

    /** Email the invoice (or, when overdue, a payment reminder) to the customer as a PDF. */
    public function emailInvoice(bool $reminder, ActivityLogger $log): void
    {
        $this->authorize('sales.edit');
        $this->sale->load(['invoice', 'customer']);
        $to = $this->sale->customer?->email;

        if ($this->sale->isCancelled() || ! $to) {
            $this->addError('email', $this->sale->isCancelled() ? 'A cancelled invoice cannot be emailed.' : 'This customer has no email address on file.');

            return;
        }

        $company = app(CurrentCompany::class)->get();
        Mail::to($to)->send(new InvoiceMail($this->sale, $company, $reminder));
        $log->log($reminder ? 'invoice.reminder_sent' : 'invoice.emailed', $this->sale, ['to' => $to]);
        $this->notice = ($reminder ? 'Reminder' : 'Invoice').' emailed to '.$to.'.';
    }

    public function closeModals(): void
    {
        $this->showPayment = $this->showCancel = $this->showEdit = false;
        $this->voidPaymentId = null;
    }

    public function render()
    {
        $this->sale->load(['items', 'invoice', 'customer', 'user', 'payments.user']);

        return view('livewire.sales.show', [
            'paymentMethods' => app(CurrentCompany::class)->get()->paymentMethods(),
            'currency' => app(CurrentCompany::class)->get()->currency,
            'voidPayment' => $this->voidPaymentId ? Payment::find($this->voidPaymentId) : null,
        ]);
    }
}
