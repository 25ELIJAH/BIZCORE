<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FinanceEntry;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\FinanceService;
use App\Services\GoodsReceiptService;
use App\Services\PaymentService;
use App\Services\PurchaseOrderService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Follows one business cycle through every module and checks that the
 * stock ledger, documents, finance entries and reports all agree.
 */
class CrossModuleIntegrationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
    }

    /** Runs a full buy -> receive -> sell -> collect -> pay cycle and returns the pieces. */
    private function cycle(): array
    {
        $product = Product::factory()->create(['selling_price' => 20]);

        $orders = app(PurchaseOrderService::class);
        $order = $orders->saveDraft(['supplier_id' => Supplier::factory()->create()->id, 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 8]]]);
        $orders->submit($order);
        $orders->approve($order);
        app(GoodsReceiptService::class)->receive($order, [['purchase_order_item_id' => $order->items()->first()->id, 'quantity' => 10]]);
        $supplierPayment = app(SupplierPaymentService::class)->record($order->refresh(), 50, 'bank_transfer');

        $sale = app(SaleService::class)->create(['items' => [['product_id' => $product->id, 'quantity' => 4]]]);
        $payment = app(PaymentService::class)->record($sale, 30, 'cash');

        return [$product->refresh(), $order->refresh(), $supplierPayment, $sale->refresh(), $payment];
    }

    public function test_a_full_business_cycle_keeps_every_module_consistent(): void
    {
        [$product, $order, , $sale] = $this->cycle();

        $this->assertSame('6.000', $product->quantity, '10 received - 4 sold');
        $this->assertSame('80.00', $order->total);
        $this->assertSame('50.00', $order->amount_paid);
        $this->assertSame('80.00', $sale->total);
        $this->assertSame('30.00', $sale->amount_paid);

        $finance = app(FinanceService::class)->summary();
        $this->assertSame('30.00', $finance['income'], 'only money actually collected is income');
        $this->assertSame('50.00', $finance['expense'], 'only money actually paid is expense');
        $this->assertSame('-20.00', $finance['net']);

        $reports = app(ReportService::class);
        $this->assertSame('50.00', $reports->dashboard(['sales', 'finance', 'inventory', 'procurement'])['sales']['receivable']);
        $dash = $reports->dashboard(['sales', 'finance', 'inventory', 'procurement']);
        $this->assertSame('80.00', $dash['sales']['month_total']);
        $this->assertSame('30.00', $dash['finance']['income']);
        $this->assertSame('30.00', $dash['procurement']['payable']);
        $this->assertSame('48.00', $dash['inventory']['value'], '6 units at the 8.00 average cost');

        $pl = $reports->profitLoss(today()->startOfMonth()->toDateString(), today()->toDateString());
        $this->assertSame('-20.00', $pl['totals']['amount']);
    }

    public function test_voiding_payments_reverses_documents_finance_and_reports_together(): void
    {
        [, $order, $supplierPayment, $sale, $payment] = $this->cycle();

        app(PaymentService::class)->void($payment, 'Bounced');
        app(SupplierPaymentService::class)->void($supplierPayment, 'Entered twice');

        $this->assertSame('0.00', $sale->refresh()->amount_paid);
        $this->assertSame('0.00', $order->refresh()->amount_paid);

        $finance = app(FinanceService::class)->summary();
        $this->assertSame('0.00', $finance['income']);
        $this->assertSame('0.00', $finance['expense']);

        $dash = app(ReportService::class)->dashboard(['sales', 'procurement']);
        $this->assertSame('80.00', $dash['sales']['receivable']);
        $this->assertSame('80.00', $dash['procurement']['payable']);
    }

    public function test_cancelling_a_sale_returns_stock_and_leaves_the_ledger_balanced(): void
    {
        [$product, , , $sale, $payment] = $this->cycle();

        app(PaymentService::class)->void($payment, 'Refunded');
        app(SaleService::class)->cancel($sale, 'Customer returned the goods');

        $this->assertSame('10.000', $product->refresh()->quantity);
        $this->assertSame($product->quantity, $this->ledgerSum($product));
    }

    public function test_data_integrity_invariants_hold_after_a_busy_period(): void
    {
        [$product, , $supplierPayment] = $this->cycle();
        $extra = Product::factory()->create(['selling_price' => 5]);
        app(\App\Services\StockService::class)->add($extra, 25, \App\Enums\StockMovementType::Addition, 2);
        $second = app(SaleService::class)->create(['items' => [['product_id' => $extra->id, 'quantity' => 3], ['product_id' => $product->id, 'quantity' => 1]], 'payment' => ['amount' => 10, 'method' => 'cash']]);
        app(PaymentService::class)->record($second, 5, 'cash');
        app(SupplierPaymentService::class)->void($supplierPayment, 'Wrong supplier');
        app(FinanceService::class)->recordManual(['type' => 'expense', 'category_id' => app(FinanceService::class)->saveCategory('expense', 'Rent')->id, 'amount' => 100, 'entry_date' => today()->toDateString(), 'description' => 'Office rent']);

        // 1. Stock on hand equals the sum of the append-only ledger for every product.
        foreach (Product::all() as $p) {
            $this->assertSame($p->quantity, $this->ledgerSum($p), "ledger mismatch for {$p->name}");
        }

        // 2. Document balances equal the sum of their active payments.
        foreach (Sale::all() as $s) {
            $this->assertSame(
                bcadd((string) Payment::active()->where('sale_id', $s->id)->sum('amount'), '0', 2),
                (string) $s->amount_paid,
                "sale {$s->id} amount_paid drifted"
            );
        }
        foreach (PurchaseOrder::all() as $o) {
            $this->assertSame(
                bcadd((string) SupplierPayment::active()->where('purchase_order_id', $o->id)->sum('amount'), '0', 2),
                (string) $o->amount_paid,
                "PO {$o->po_number} amount_paid drifted"
            );
        }

        // 3. Finance equals active payments plus manual entries - nothing double counted or lost.
        $income = bcadd((string) Payment::active()->sum('amount'), '0', 2);
        $expense = bcadd(bcadd((string) SupplierPayment::active()->sum('amount'), '0', 2), (string) FinanceEntry::active()->whereNull('source_type')->where('type', 'expense')->sum('amount'), 2);
        $summary = app(FinanceService::class)->summary();
        $this->assertSame($income, $summary['income']);
        $this->assertSame($expense, $summary['expense']);

        // 4. Document numbers are unique within the company.
        $this->assertSame(Invoice::count(), Invoice::distinct()->count("invoice_number"));
        $this->assertSame(PurchaseOrder::count(), PurchaseOrder::distinct()->count('po_number'));

        // 5. Nothing is overpaid.
        $this->assertSame(0, Sale::whereColumn('amount_paid', '>', 'total')->count());
        $this->assertSame(0, PurchaseOrder::whereColumn('amount_paid', '>', 'total')->count());
    }

    public function test_dashboard_query_count_does_not_grow_with_data_volume(): void
    {
        $this->cycle();
        $count = function () {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            app(ReportService::class)->dashboard(['sales', 'finance', 'inventory', 'procurement', 'hr']);

            return count(\DB::getQueryLog());
        };
        $small = $count();

        for ($i = 0; $i < 5; $i++) {
            $this->cycle();
        }

        $this->assertSame($small, $count(), 'dashboard queries must not depend on the number of records (N+1)');
        $this->assertLessThan(40, $small);
    }

    private function ledgerSum(Product $product): string
    {
        return bcadd((string) StockMovement::where('product_id', $product->id)->sum('quantity'), '0', 3);
    }
}
