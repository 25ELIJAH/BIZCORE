<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Payments made to suppliers against approved (or later) purchase orders. */
class SupplierPaymentService
{
    public function __construct(private CurrentCompany $currentCompany, private ActivityLogger $activityLogger, private FinanceService $finance) {}

    public function record(PurchaseOrder $order, string|int|float $amount, string $method, CarbonInterface|string|null $paidAt = null, ?string $reference = null, ?string $idempotencyKey = null): SupplierPayment
    {
        return DB::transaction(function () use ($order, $amount, $method, $paidAt, $reference, $idempotencyKey) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($idempotencyKey !== null && ($existing = SupplierPayment::where('idempotency_key', $idempotencyKey)->first())) {
                if ($existing->purchase_order_id !== $locked->id) {
                    throw new BusinessRuleException('This payment reference was already used for a different order.');
                }

                return $existing;
            }

            if (! $locked->isPayable()) {
                throw new BusinessRuleException('Payments can only be recorded against approved purchase orders.');
            }

            if (! is_numeric((string) $amount) || Money::compare(Money::round($amount), '0.00') <= 0) {
                throw new BusinessRuleException('Payment amount must be greater than zero.');
            }
            $amount = Money::round($amount);

            if (! array_key_exists($method, $this->currentCompany->get()->paymentMethods())) {
                throw new BusinessRuleException('That payment method is not available for this company.');
            }

            $balance = $locked->balance();
            if (Money::compare($amount, $balance) > 0) {
                throw new BusinessRuleException("Payment of {$amount} exceeds the outstanding balance of {$balance}.");
            }

            $payment = (new SupplierPayment)->forceFill([
                'purchase_order_id' => $locked->id,
                'supplier_id' => $locked->supplier_id,
                'user_id' => Auth::id(),
                'amount' => $amount,
                'method' => $method,
                'paid_at' => $paidAt ?? today(),
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
            ]);
            $payment->save();

            $this->refreshPaid($locked);
            $this->finance->recordSupplierPayment($payment, $locked->po_number);
            $this->activityLogger->log('supplier_payment.recorded', $payment, ['po_number' => $locked->po_number, 'amount' => $amount, 'method' => $method]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        }, 5);
    }

    public function void(SupplierPayment $payment, string $reason): SupplierPayment
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('A reason is required to void a payment.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($payment->purchase_order_id);
            $locked = SupplierPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->isVoided()) {
                throw new BusinessRuleException('This payment has already been voided.');
            }

            $locked->forceFill(['voided_at' => now(), 'voided_by' => Auth::id(), 'void_reason' => $reason])->save();
            $this->refreshPaid($order);
            $this->finance->voidSource(FinanceService::SOURCE_SUPPLIER_PAYMENT, $locked->id, $reason);
            $this->activityLogger->log('supplier_payment.voided', $locked, ['po_number' => $order->po_number, 'amount' => (string) $locked->amount, 'reason' => $reason]);

            return $locked;
        });
    }

    private function refreshPaid(PurchaseOrder $order): void
    {
        $paid = Money::round((string) SupplierPayment::where('purchase_order_id', $order->id)->active()->sum('amount'));
        $order->forceFill(['amount_paid' => $paid])->save();
    }
}
