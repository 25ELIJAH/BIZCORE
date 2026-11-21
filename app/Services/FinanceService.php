<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Income and expense records.
 *  - Derived entries: every customer payment becomes income, every supplier payment becomes expense (once, keyed on the
 *    source payment). Voiding the payment voids the entry. They cannot be edited or voided by hand.
 *  - Manual entries: anything else (rent, salaries, other income...), editable, voidable with a reason.
 */
class FinanceService
{
    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';

    public const SALES_CATEGORY = 'Sales revenue';

    public const PURCHASES_CATEGORY = 'Supplier purchases';

    public function __construct(private ActivityLogger $activityLogger) {}

    // ---- categories ---------------------------------------------------------

    public function saveCategory(string $type, string $name, ?FinanceCategory $category = null, bool $active = true): FinanceCategory
    {
        $name = trim($name);
        if (! in_array($type, [FinanceCategory::INCOME, FinanceCategory::EXPENSE], true)) {
            throw new BusinessRuleException('Category type must be income or expense.');
        }
        if ($name === '') {
            throw new BusinessRuleException('Category name is required.');
        }
        if ($category?->is_system) {
            throw new BusinessRuleException('System categories cannot be changed.');
        }
        if ($category && $category->type !== $type && $category->entries()->exists()) {
            throw new BusinessRuleException('A category that already has entries cannot change type.');
        }
        $duplicate = FinanceCategory::where('type', $type)->where('name', $name)->when($category, fn ($q) => $q->whereKeyNot($category->id))->exists();
        if ($duplicate) {
            throw new BusinessRuleException("An {$type} category called \"{$name}\" already exists.");
        }

        $category ??= new FinanceCategory;
        $isNew = ! $category->exists;
        $category->forceFill(['type' => $type, 'name' => $name, 'is_active' => $active] + ($isNew ? ['is_system' => false] : []))->save();
        $this->activityLogger->log($isNew ? 'finance_category.created' : 'finance_category.updated', $category, ['name' => $name, 'type' => $type]);

        return $category;
    }

    public function systemCategory(string $type, string $name): FinanceCategory
    {
        $existing = FinanceCategory::where('type', $type)->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $category = (new FinanceCategory)->forceFill(['type' => $type, 'name' => $name, 'is_system' => true, 'is_active' => true]);
        $category->save();

        return $category;
    }

    // ---- manual entries -----------------------------------------------------

    /** @param array{category_id: int, amount: string|int|float, entry_date: string, description: string, reference?: string|null} $data */
    public function recordManual(array $data, ?FinanceEntry $entry = null): FinanceEntry
    {
        return DB::transaction(function () use ($data, $entry) {
            if ($entry) {
                $entry = FinanceEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
                if ($entry->isDerived()) {
                    throw new BusinessRuleException('This entry comes from a payment. Change it by voiding the payment.');
                }
                if ($entry->isVoided()) {
                    throw new BusinessRuleException('Voided entries cannot be edited.');
                }
            }

            $category = FinanceCategory::find($data['category_id'] ?? 0)
                ?? throw new BusinessRuleException('Choose a valid category.');
            if (! $category->is_active && $category->id !== $entry?->finance_category_id) {
                throw new BusinessRuleException('That category is inactive.');
            }

            $amount = (string) ($data['amount'] ?? '');
            if (! is_numeric($amount) || Money::compare(Money::round($amount), '0.00') <= 0) {
                throw new BusinessRuleException('Amount must be greater than zero.');
            }
            $description = trim((string) ($data['description'] ?? ''));
            if ($description === '') {
                throw new BusinessRuleException('A description is required.');
            }

            $entry ??= new FinanceEntry;
            $isNew = ! $entry->exists;
            $entry->forceFill([
                'finance_category_id' => $category->id,
                'type' => $category->type,
                'amount' => Money::round($amount),
                'entry_date' => Carbon::parse($data['entry_date'] ?? today()),
                'description' => $description,
                'reference' => $data['reference'] ?? null,
            ] + ($isNew ? ['user_id' => Auth::id()] : []))->save();

            $this->activityLogger->log($isNew ? 'finance_entry.created' : 'finance_entry.updated', $entry, ['type' => $entry->type, 'amount' => (string) $entry->amount]);

            return $entry;
        });
    }

    public function voidManual(FinanceEntry $entry, string $reason): FinanceEntry
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('A reason is required to void an entry.');
        }

        return DB::transaction(function () use ($entry, $reason) {
            $locked = FinanceEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            if ($locked->isDerived()) {
                throw new BusinessRuleException('This entry comes from a payment. Void the payment instead.');
            }
            if ($locked->isVoided()) {
                throw new BusinessRuleException('This entry has already been voided.');
            }
            $this->markVoided($locked, $reason);
            $this->activityLogger->log('finance_entry.voided', $locked, ['amount' => (string) $locked->amount, 'reason' => $reason]);

            return $locked;
        });
    }

    // ---- derived entries (called by the payment services, inside their transaction) ----

    public function recordCustomerPayment(Payment $payment, string $label): FinanceEntry
    {
        return $this->derive(self::SOURCE_PAYMENT, $payment->id, FinanceCategory::INCOME, self::SALES_CATEGORY, (string) $payment->amount, $payment->paid_at, "Customer payment: {$label}", $payment->reference);
    }

    public function recordSupplierPayment(SupplierPayment $payment, string $label): FinanceEntry
    {
        return $this->derive(self::SOURCE_SUPPLIER_PAYMENT, $payment->id, FinanceCategory::EXPENSE, self::PURCHASES_CATEGORY, (string) $payment->amount, $payment->paid_at, "Supplier payment: {$label}", $payment->reference);
    }

    /** Void the entry that came from a payment. Safe to call when there is none. */
    public function voidSource(string $sourceType, int $sourceId, string $reason): void
    {
        $entry = FinanceEntry::query()->lockForUpdate()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($entry && ! $entry->isVoided()) {
            $this->markVoided($entry, "Payment voided: {$reason}");
        }
    }

    private function derive(string $sourceType, int $sourceId, string $type, string $categoryName, string $amount, $date, string $description, ?string $reference): FinanceEntry
    {
        // unique(company, source_type, source_id) + this lookup: the same payment can never be counted twice
        $existing = FinanceEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing) {
            return $existing;
        }

        $entry = (new FinanceEntry)->forceFill([
            'finance_category_id' => $this->systemCategory($type, $categoryName)->id,
            'user_id' => Auth::id(),
            'type' => $type,
            'amount' => Money::round($amount),
            'entry_date' => $date,
            'description' => $description,
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $entry->save();

        return $entry;
    }

    private function markVoided(FinanceEntry $entry, string $reason): void
    {
        $entry->forceFill(['voided_at' => now(), 'voided_by' => Auth::id(), 'void_reason' => $reason])->save();
    }

    // ---- reporting ----------------------------------------------------------

    /**
     * Totals for a period from non-voided entries.
     *
     * @return array{income: string, expense: string, net: string, income_by_category: array<string, string>, expense_by_category: array<string, string>}
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $rows = FinanceEntry::active()
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->join('finance_categories', 'finance_categories.id', '=', 'finance_entries.finance_category_id')
            ->groupBy('finance_entries.type', 'finance_categories.name')
            ->selectRaw('finance_entries.type as type, finance_categories.name as category, SUM(finance_entries.amount) as total')
            ->get();

        $out = ['income' => '0.00', 'expense' => '0.00', 'income_by_category' => [], 'expense_by_category' => []];
        foreach ($rows as $row) {
            $total = Money::round((string) $row->total);
            $out[$row->type] = Money::add($out[$row->type], $total);
            $out["{$row->type}_by_category"][$row->category] = $total;
        }
        $out['net'] = Money::sub($out['income'], $out['expense']);

        return $out;
    }
}
