<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Services\FinanceService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

/**
 * Creates the income / expense entries for payments recorded before the finance module existed.
 * Idempotent: a payment that already has an entry is skipped, so it is safe to run again.
 */
class SyncFinanceFromPayments extends Command
{
    protected $signature = 'finance:sync-payments';

    protected $description = 'Create missing finance entries for existing customer and supplier payments';

    public function handle(CurrentCompany $tenant, FinanceService $finance): int
    {
        $created = 0;

        foreach (Company::all() as $company) {
            $tenant->runAs($company, function () use ($finance, &$created) {
                foreach (Payment::with('sale.invoice')->active()->get() as $payment) {
                    $before = \App\Models\FinanceEntry::where('source_type', FinanceService::SOURCE_PAYMENT)->where('source_id', $payment->id)->exists();
                    $finance->recordCustomerPayment($payment, $payment->sale?->invoice?->invoice_number ?? "sale #{$payment->sale_id}");
                    $created += $before ? 0 : 1;
                }
                foreach (SupplierPayment::with('purchaseOrder')->active()->get() as $payment) {
                    $before = \App\Models\FinanceEntry::where('source_type', FinanceService::SOURCE_SUPPLIER_PAYMENT)->where('source_id', $payment->id)->exists();
                    $finance->recordSupplierPayment($payment, $payment->purchaseOrder?->po_number ?? "order #{$payment->purchase_order_id}");
                    $created += $before ? 0 : 1;
                }
            });
        }

        $this->info("Created {$created} finance entries.");

        return self::SUCCESS;
    }
}
