<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use App\Models\FinanceCategory;
use App\Models\FinanceEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\FinanceService;
use App\Services\PaymentService;
use App\Services\PurchaseOrderService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
        $this->finance = app(FinanceService::class);
    }

    private function category(string $type = 'expense', string $name = 'Rent'): FinanceCategory
    {
        return $this->finance->saveCategory($type, $name);
    }

    private function sale(int $price = 100)
    {
        $p = Product::factory()->create(['selling_price' => $price]);
        app(StockService::class)->add($p, 50, StockMovementType::Purchase, 1);

        return app(SaleService::class)->create(['items' => [['product_id' => $p->id, 'quantity' => 1]]]);
    }

    private function approvedPo(int $cost = 100)
    {
        $svc = app(PurchaseOrderService::class);
        $po = $svc->saveDraft(['supplier_id' => Supplier::factory()->create()->id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_cost' => $cost]]]);
        $svc->submit($po);
        $svc->approve($po);

        return $po;
    }

    public function test_manual_entry_records_type_from_category_and_totals(): void
    {
        $rent = $this->category('expense', 'Rent');
        $grant = $this->category('income', 'Grants');

        $this->finance->recordManual(['category_id' => $rent->id, 'amount' => 300, 'entry_date' => today()->toDateString(), 'description' => 'May rent']);
        $this->finance->recordManual(['category_id' => $grant->id, 'amount' => '1000.50', 'entry_date' => today()->toDateString(), 'description' => 'Grant']);

        $summary = $this->finance->summary();
        $this->assertSame('1000.50', $summary['income']);
        $this->assertSame('300.00', $summary['expense']);
        $this->assertSame('700.50', $summary['net']);
        $this->assertSame('300.00', $summary['expense_by_category']['Rent']);
    }

    public function test_validation_rules(): void
    {
        $cat = $this->category();
        foreach ([
            ['amount' => 0, 'description' => 'x'],
            ['amount' => -5, 'description' => 'x'],
            ['amount' => 'abc', 'description' => 'x'],
            ['amount' => 5, 'description' => '  '],
        ] as $bad) {
            try {
                $this->finance->recordManual($bad + ['category_id' => $cat->id, 'entry_date' => today()->toDateString()]);
                $this->fail('Expected BusinessRuleException');
            } catch (BusinessRuleException) {
            }
        }
        $this->assertSame(0, FinanceEntry::count());
    }

    public function test_inactive_category_rejected_for_new_entries(): void
    {
        $cat = $this->finance->saveCategory('expense', 'Old', null, false);

        $this->expectException(BusinessRuleException::class);
        $this->finance->recordManual(['category_id' => $cat->id, 'amount' => 5, 'entry_date' => today()->toDateString(), 'description' => 'x']);
    }

    public function test_duplicate_category_names_per_type_rejected_but_allowed_across_types(): void
    {
        $this->category('expense', 'Misc');
        $this->category('income', 'Misc');

        $this->expectException(BusinessRuleException::class);
        $this->category('expense', 'Misc');
    }

    public function test_voiding_removes_entry_from_totals_and_needs_reason(): void
    {
        $entry = $this->finance->recordManual(['category_id' => $this->category()->id, 'amount' => 50, 'entry_date' => today()->toDateString(), 'description' => 'x']);

        try {
            $this->finance->voidManual($entry, ' ');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->finance->voidManual($entry, 'Duplicate');
        $this->assertSame('0.00', $this->finance->summary()['expense']);
        $this->assertSame(1, FinanceEntry::count()); // kept for the audit trail

        $this->expectException(BusinessRuleException::class);
        $this->finance->recordManual(['category_id' => $entry->finance_category_id, 'amount' => 1, 'entry_date' => today()->toDateString(), 'description' => 'edit'], $entry);
    }

    public function test_customer_payment_creates_income_once_and_void_reverses_it(): void
    {
        $sale = $this->sale(100);
        $payment = app(PaymentService::class)->record($sale, 40, 'cash', null, null, 'k1');
        app(PaymentService::class)->record($sale, 40, 'cash', null, null, 'k1'); // duplicate submission

        $this->assertSame(1, FinanceEntry::count());
        $entry = FinanceEntry::first();
        $this->assertSame('income', $entry->type);
        $this->assertSame('40.00', (string) $entry->amount);
        $this->assertTrue($entry->isDerived());
        $this->assertSame('40.00', $this->finance->summary()['income']);

        app(PaymentService::class)->void($payment, 'Mistake');
        $this->assertSame('0.00', $this->finance->summary()['income']);
        $this->assertTrue($entry->refresh()->isVoided());
    }

    public function test_payment_made_with_the_sale_is_counted_as_income(): void
    {
        $p = Product::factory()->create(['selling_price' => 20]);
        app(StockService::class)->add($p, 5, StockMovementType::Purchase, 1);
        app(SaleService::class)->create(['items' => [['product_id' => $p->id, 'quantity' => 2]], 'payment' => ['amount' => 15, 'method' => 'cash']]);

        $this->assertSame('15.00', $this->finance->summary()['income']);
    }

    public function test_failed_sale_creates_no_income(): void
    {
        $p = Product::factory()->create(['selling_price' => 20]);

        try {
            app(SaleService::class)->create(['items' => [['product_id' => $p->id, 'quantity' => 2]], 'payment' => ['amount' => 15, 'method' => 'cash']]);
            $this->fail('Expected exception');
        } catch (\Throwable) {
        }

        $this->assertSame(0, FinanceEntry::count());
    }

    public function test_supplier_payment_creates_expense_and_void_reverses_it(): void
    {
        $po = $this->approvedPo(100);
        $payment = app(SupplierPaymentService::class)->record($po, 60, 'cash');

        $this->assertSame('60.00', $this->finance->summary()['expense']);
        $this->assertSame('Supplier purchases', $this->finance->summary()['expense_by_category'] ? array_key_first($this->finance->summary()['expense_by_category']) : '');

        app(SupplierPaymentService::class)->void($payment, 'Wrong supplier');
        $this->assertSame('0.00', $this->finance->summary()['expense']);
    }

    public function test_derived_entries_cannot_be_edited_or_voided_by_hand(): void
    {
        app(PaymentService::class)->record($this->sale(), 10, 'cash');
        $entry = FinanceEntry::first();

        try {
            $this->finance->voidManual($entry, 'no');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }
        $this->expectException(BusinessRuleException::class);
        $this->finance->recordManual(['category_id' => $entry->finance_category_id, 'amount' => 999, 'entry_date' => today()->toDateString(), 'description' => 'x'], $entry);
    }

    public function test_system_categories_cannot_be_renamed(): void
    {
        app(PaymentService::class)->record($this->sale(), 10, 'cash');
        $system = FinanceCategory::where('is_system', true)->first();

        $this->expectException(BusinessRuleException::class);
        $this->finance->saveCategory('income', 'Renamed', $system);
    }

    public function test_summary_respects_date_range(): void
    {
        $cat = $this->category();
        $this->finance->recordManual(['category_id' => $cat->id, 'amount' => 10, 'entry_date' => '2026-01-10', 'description' => 'a']);
        $this->finance->recordManual(['category_id' => $cat->id, 'amount' => 20, 'entry_date' => '2026-02-10', 'description' => 'b']);

        $this->assertSame('20.00', $this->finance->summary('2026-02-01', '2026-02-28')['expense']);
        $this->assertSame('30.00', $this->finance->summary()['expense']);
    }

    public function test_finance_data_is_isolated_between_companies(): void
    {
        $entry = $this->finance->recordManual(['category_id' => $this->category()->id, 'amount' => 10, 'entry_date' => today()->toDateString(), 'description' => 'x']);

        $other = Company::factory()->create();
        app(CurrentCompany::class)->set($other);

        $this->assertSame(0, FinanceEntry::count());
        $this->assertSame(0, FinanceCategory::count());
        $this->assertSame('0.00', $this->finance->summary()['expense']);
        $this->expectException(ModelNotFoundException::class);
        $this->finance->voidManual($entry, 'hack');
    }
}
