<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Models\Sale;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private CurrentCompany $currentCompany, private ActivityLogger $activityLogger, private FinanceService $finance) {}

    /**
     * Record a customer payment. Partial payments are supported; overpayment is refused.
     * Passing the same $idempotencyKey twice returns the first payment instead of recording a duplicate.
     */
    public function record(Sale $sale, string|int|float $amount, string $method, CarbonInterface|string|null $paidAt = null, ?string $reference = null, ?string $idempotencyKey = null, array $details = []): Payment
    {
        return DB::transaction(function () use ($sale, $amount, $method, $paidAt, $reference, $idempotencyKey, $details) {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($idempotencyKey !== null && ($existing = Payment::where('idempotency_key', $idempotencyKey)->first())) {
                if ($existing->sale_id !== $locked->id) {
                    throw new BusinessRuleException('This payment reference was already used for a different sale.');
                }

                return $existing; // duplicate submission: nothing new recorded
            }

            if ($locked->isCancelled()) {
                throw new BusinessRuleException('Payments cannot be recorded against a cancelled sale.');
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

            $payment = (new Payment)->forceFill([
                'sale_id' => $locked->id,
                'user_id' => Auth::id(),
                'amount' => $amount,
                'method' => $method,
                'paid_at' => $paidAt ?? today(),
                'reference' => $reference,
                'payer_name' => ($details['payer_name'] ?? null) ?: null,
                'bank_name' => ($details['bank_name'] ?? null) ?: null,
                'transacted_at' => ! empty($details['transacted_at']) ? $details['transacted_at'] : null,
                'idempotency_key' => $idempotencyKey,
            ]);
            $payment->save();

            $this->refreshPaymentStatus($locked);
            $this->finance->recordCustomerPayment($payment, $locked->invoice?->invoice_number ?? "sale #{$locked->id}");
            $this->activityLogger->log('payment.recorded', $payment, ['sale_id' => $locked->id, 'amount' => $amount, 'method' => $method]);

            $sale->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        }, 5);
    }

    /** Reverse a mistaken payment. The row is kept (voided with a reason), never deleted. */
    public function void(Payment $payment, string $reason): Payment
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('A reason is required to void a payment.');
        }

        return DB::transaction(function () use ($payment, $reason) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($payment->sale_id);
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->isVoided()) {
                throw new BusinessRuleException('This payment has already been voided.');
            }

            $locked->forceFill(['voided_at' => now(), 'voided_by' => Auth::id(), 'void_reason' => $reason])->save();
            $this->refreshPaymentStatus($sale);
            $this->finance->voidSource(FinanceService::SOURCE_PAYMENT, $locked->id, $reason);
            $this->activityLogger->log('payment.voided', $locked, ['sale_id' => $sale->id, 'amount' => (string) $locked->amount, 'reason' => $reason]);

            return $locked;
        });
    }

    /** Recompute amount_paid and payment_status from the non-voided payments (source of truth). */
    public function refreshPaymentStatus(Sale $sale): void
    {
        $paid = (string) Payment::where('sale_id', $sale->id)->active()->sum('amount');
        $paid = Money::round($paid);

        $status = match (true) {
            Money::compare($paid, '0.00') <= 0 => Sale::PAYMENT_UNPAID,
            Money::compare($paid, (string) $sale->total) >= 0 => Sale::PAYMENT_PAID,
            default => Sale::PAYMENT_PARTIAL,
        };

        $sale->forceFill(['amount_paid' => $paid, 'payment_status' => $status])->save();
    }
}
