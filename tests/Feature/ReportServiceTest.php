<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\FinanceService;
use App\Services\PaymentService;
use App\Services\PurchaseOrderService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private ReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
        $this->reports = app(ReportService::class);
    }

    private function product(int $stock, int $price, int $cost, array $extra = []): Product
    {
        $p = Product::factory()->create(['selling_price' => $price, 'purchase_price' => $cost] + $extra);
        app(StockService::class)->add($p, $stock, StockMovementType::Purchase, $cost);

        return $p->refresh();
    }

    private function sell(Product $p, int $qty, array $extra = []): Sale
    {
        return app(SaleService::class)->create(['items' => [['product_id' => $p->id, 'quantity' => $qty]]] + $extra);
    }

    public function test_sales_report_lists_completed_sales_with_totals_and_excludes_cancelled(): void
    {
        $p = $this->product(50, 10, 6);
        $this->sell($p, 3, ['payment' => ['amount' => 10, 'method' => 'cash']]);
        $cancelled = $this->sell($p, 5);
        app(SaleService::class)->cancel($cancelled, 'Test');

        $r = $this->reports->sales(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertCount(1, $r['rows']);
        $this->assertSame('30.00', $r['totals']['total']);
        $this->assertSame('10.00', $r['totals']['paid']);
        $this->assertSame('20.00', $r['totals']['balance']);
    }

    public function test_sales_report_respects_the_date_range(): void
    {
        $p = $this->product(50, 10, 6);
        $this->sell($p, 1, ['sale_date' => '2026-01-15']);
        $this->sell($p, 1, ['sale_date' => '2026-03-15']);

        $r = $this->reports->sales('2026-03-01', '2026-03-31');

        $this->assertCount(1, $r['rows']);
        $this->assertSame('2026-03-15', $r['rows'][0]['date']);
    }

    public function test_product_profitability_uses_snapshot_cost(): void
    {
        $p = $this->product(50, 10, 6);
        $this->sell($p, 4);

        $r = $this->reports->productProfit(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertSame('40.00', $r['rows'][0]['revenue']);
        $this->assertSame('24.00', $r['rows'][0]['cost']);
        $this->assertSame('16.00', $r['rows'][0]['profit']);
        $this->assertSame('40.0%', $r['rows'][0]['margin']);
        $this->assertSame('16.00', $r['totals']['profit']);
    }

    public function test_inventory_valuation_and_low_stock_flag(): void
    {
        $this->product(10, 20, 5, ['name' => 'Alpha']);
        $this->product(2, 20, 4, ['name' => 'Beta', 'min_stock' => 5]);

        $r = $this->reports->inventory();

        $this->assertSame('58.00', $r['totals']['value']); // 10*5 + 2*4
        $beta = collect($r['rows'])->firstWhere('name', 'Beta');
        $this->assertSame('Low stock', $beta['status']);
        $this->assertSame('OK', collect($r['rows'])->firstWhere('name', 'Alpha')['status']);
    }

    public function test_receivables_are_bucketed_by_days_past_due(): void
    {
        $p = $this->product(100, 10, 5);
        $c = Customer::factory()->create();
        $this->sell($p, 1, ['customer_id' => $c->id, 'due_date' => today()->addDays(5)->toDateString()]);    // not yet due: 10
        $this->sell($p, 2, ['customer_id' => $c->id, 'due_date' => today()->subDays(10)->toDateString(), 'sale_date' => today()->subDays(20)->toDateString()]); // 1-30: 20
        $this->sell($p, 3, ['customer_id' => $c->id, 'due_date' => today()->subDays(45)->toDateString(), 'sale_date' => today()->subDays(50)->toDateString()]); // 31-60: 30
        $this->sell($p, 4, ['customer_id' => $c->id, 'due_date' => today()->subDays(200)->toDateString(), 'sale_date' => today()->subDays(210)->toDateString()]); // >90: 40
        $paid = $this->sell($p, 1, ['customer_id' => $c->id, 'payment' => ['amount' => 10, 'method' => 'cash']]); // fully paid: excluded

        $r = $this->reports->receivables();

        $this->assertCount(1, $r['rows']);
        $row = $r['rows'][0];
        $this->assertSame('10.00', $row['current']);
        $this->assertSame('20.00', $row['d30']);
        $this->assertSame('30.00', $row['d60']);
        $this->assertSame('0.00', $row['d90']);
        $this->assertSame('40.00', $row['over90']);
        $this->assertSame('100.00', $row['total']);
        $this->assertSame('100.00', $r['totals']['total']);
    }

    public function test_receivables_ignore_cancelled_sales_and_count_partial_payments(): void
    {
        $p = $this->product(100, 10, 5);
        $a = $this->sell($p, 5, ['payment' => ['amount' => 20, 'method' => 'cash']]); // owes 30
        $b = $this->sell($p, 9);
        app(SaleService::class)->cancel($b, 'oops');

        $this->assertSame('30.00', $this->reports->receivables()['totals']['total']);
    }

    public function test_payables_report_matches_supplier_balances(): void
    {
        $svc = app(PurchaseOrderService::class);
        $supplier = Supplier::factory()->create(['name' => 'Acme Wholesale']);
        $mk = function (int $cost) use ($svc, $supplier) {
            $po = $svc->saveDraft(['supplier_id' => $supplier->id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_cost' => $cost]]]);
            $svc->submit($po);
            $svc->approve($po);

            return $po;
        };
        $a = $mk(100);
        $mk(50);
        app(SupplierPaymentService::class)->record($a, 40, 'cash');
        $draft = $svc->saveDraft(['supplier_id' => $supplier->id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_cost' => 999]]]); // draft: not owed

        $r = $this->reports->payables();

        $this->assertCount(1, $r['rows']);
        $this->assertSame('110.00', $r['rows'][0]['balance']);
        $this->assertSame('150.00', $r['rows'][0]['total']);
        $this->assertSame($supplier->fresh()->outstandingBalance(), $r['totals']['balance']);
    }

    public function test_profit_and_loss_combines_payments_and_manual_entries(): void
    {
        $p = $this->product(10, 100, 40);
        $sale = $this->sell($p, 1, ['payment' => ['amount' => 100, 'method' => 'cash']]);
        $finance = app(FinanceService::class);
        $rent = $finance->saveCategory('expense', 'Rent');
        $finance->recordManual(['category_id' => $rent->id, 'amount' => 30, 'entry_date' => today()->toDateString(), 'description' => 'Rent']);

        $r = $this->reports->profitLoss(today()->startOfMonth()->toDateString(), today()->toDateString());

        $this->assertSame('70.00', $r['totals']['amount']);
        $labels = collect($r['rows'])->pluck('amount', 'category');
        $this->assertSame('100.00', $labels['Total income']);
        $this->assertSame('30.00', $labels['Total expenses']);
    }

    public function test_leave_balances_report_lists_active_employees(): void
    {
        $e = app(\App\Services\EmployeeService::class)->save(['first_name' => 'Zed', 'last_name' => 'One', 'hire_date' => '2024-01-01', 'annual_leave_days' => 10], null, true);

        $r = $this->reports->leaveBalances();

        $this->assertCount(1, $r['rows']);
        $this->assertSame('10.0', $r['rows'][0]['remaining']);
    }

    public function test_dashboard_only_computes_requested_sections(): void
    {
        $p = $this->product(10, 10, 5);
        $this->sell($p, 2);

        $none = $this->reports->dashboard([]);
        $sales = $this->reports->dashboard(['sales']);

        $this->assertSame([], $none);
        $this->assertSame(['sales'], array_keys($sales));
        $this->assertSame('20.00', $sales['sales']['month_total']);
        $this->assertSame('20.00', $sales['sales']['receivable']);
        $this->assertCount(6, $sales['sales']['trend']['labels']);
        $this->assertSame('20.00', end($sales['sales']['trend']['values']));
        $this->assertSame('0.00', $sales['sales']['trend']['values'][0]);
        $this->assertCount(1, $sales['sales']['top_products']['labels']);
        $this->assertSame(['20.00'], $sales['sales']['top_products']['values']);
    }

    public function test_dashboard_inventory_and_procurement_and_hr_figures(): void
    {
        $this->product(10, 10, 5);
        $this->product(1, 10, 5, ['min_stock' => 3]);
        $e = app(\App\Services\EmployeeService::class)->save(['first_name' => 'Zed', 'last_name' => 'One', 'hire_date' => '2024-01-01'], null, true);

        $d = $this->reports->dashboard(['inventory', 'procurement', 'hr', 'finance']);

        $this->assertSame('55.00', $d['inventory']['value']);
        $this->assertSame(1, $d['inventory']['low_stock']);
        $this->assertSame(1, $d['hr']['headcount']);
        $this->assertSame('0.00', $d['procurement']['payable']);
        $this->assertSame('0.00', $d['finance']['net']);
    }

    public function test_reports_never_include_another_companys_data(): void
    {
        $p = $this->product(50, 10, 6);
        $this->sell($p, 3);

        $other = Company::factory()->create();
        app(CurrentCompany::class)->set($other);

        $this->assertSame([], $this->reports->sales('2000-01-01', '2100-01-01')['rows']);
        $this->assertSame([], $this->reports->inventory()['rows']);
        $this->assertSame([], $this->reports->receivables()['rows']);
        $this->assertSame('0.00', $this->reports->dashboard(['sales'])['sales']['month_total']);
    }

    public function test_unknown_report_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reports->build('nope');
    }
}
