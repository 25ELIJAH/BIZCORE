<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private SaleService $sales;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('sales-officer', $this->company));
        app(CurrentCompany::class)->set($this->company);
        $this->sales = app(SaleService::class);
        $this->payments = app(PaymentService::class);
    }

    private function product(string|int $stock = 100, string|int $price = 10, string|int $cost = 6): Product
    {
        $product = Product::factory()->create(['selling_price' => $price, 'purchase_price' => $cost]);
        app(StockService::class)->add($product, $stock, StockMovementType::Purchase, $cost);

        return $product->refresh();
    }

    private function sell(array $items, array $extra = []): Sale
    {
        return $this->sales->create(['items' => $items] + $extra);
    }

    public function test_completed_sale_records_items_invoice_totals_and_deducts_stock(): void
    {
        $widget = $this->product(100, 10, 6);
        $customer = Customer::factory()->create();

        $sale = $this->sell([['product_id' => $widget->id, 'quantity' => 3]], ['customer_id' => $customer->id]);

        $this->assertSame('30.00', $sale->subtotal);
        $this->assertSame('30.00', $sale->total);
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertCount(1, $sale->items);
        $item = $sale->items->first();
        $this->assertSame('3.000', $item->quantity);
        $this->assertSame('6.0000', $item->unit_cost);            // cost snapshot for margin reporting
        $this->assertSame($widget->name, $item->product_name);   // name snapshot
        $this->assertSame('97.000', $widget->fresh()->quantity);

        $movement = StockMovement::where('type', StockMovementType::Sale)->firstOrFail();
        $this->assertSame('-3.000', $movement->quantity);
        $this->assertSame($sale->id, $movement->reference_id);
        $this->assertSame(Sale::class, $movement->reference_type);
    }

    public function test_discount_and_tax_are_calculated_correctly(): void
    {
        $p = $this->product(100, 25);

        $fixed = $this->sell([['product_id' => $p->id, 'quantity' => 4]], ['discount_type' => 'fixed', 'discount_value' => 20, 'tax_rate' => 10]);
        // 100 - 20 = 80; tax 8.00; total 88.00
        $this->assertSame(['100.00', '20.00', '8.00', '88.00'], [$fixed->subtotal, $fixed->discount_amount, $fixed->tax_amount, $fixed->total]);

        $percent = $this->sell([['product_id' => $p->id, 'quantity' => 2]], ['discount_type' => 'percent', 'discount_value' => 10, 'tax_rate' => 16]);
        // 50 - 5 = 45; tax 7.20; total 52.20
        $this->assertSame(['50.00', '5.00', '7.20', '52.20'], [$percent->subtotal, $percent->discount_amount, $percent->tax_amount, $percent->total]);
    }

    public function test_default_tax_rate_and_payment_terms_come_from_company_settings(): void
    {
        $this->company->update(['settings' => ['tax_rate' => 5, 'payment_terms_days' => 14]]);
        app(CurrentCompany::class)->set($this->company->fresh());
        $p = $this->product(10, 100);

        $sale = $this->sell([['product_id' => $p->id, 'quantity' => 1]], ['sale_date' => '2026-03-01']);

        $this->assertSame('105.00', $sale->total);
        $this->assertSame('2026-03-15', $sale->due_date->toDateString());
    }

    public function test_invalid_discounts_and_inputs_are_rejected(): void
    {
        $p = $this->product(10, 10);

        foreach ([
            ['discount_type' => 'fixed', 'discount_value' => 11],
            ['discount_type' => 'percent', 'discount_value' => 101],
            ['discount_type' => 'fixed', 'discount_value' => -1],
            ['tax_rate' => 150],
            ['due_date' => '2000-01-01'],
        ] as $bad) {
            try {
                $this->sell([['product_id' => $p->id, 'quantity' => 1]], $bad);
                $this->fail('Expected rejection for '.json_encode($bad));
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Sale::count());
    }

    public function test_sale_needs_items_with_positive_quantity(): void
    {
        $p = $this->product();
        $this->expectException(BusinessRuleException::class);
        $this->sell([['product_id' => $p->id, 'quantity' => 0]]);
    }

    public function test_insufficient_stock_saves_nothing_and_does_not_burn_an_invoice_number(): void
    {
        $a = $this->product(10);
        $b = $this->product(2);

        try {
            $this->sell([['product_id' => $a->id, 'quantity' => 5], ['product_id' => $b->id, 'quantity' => 3]]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame('10.000', $a->fresh()->quantity);   // first line's deduction rolled back too
        $this->assertSame('2.000', $b->fresh()->quantity);
        $this->assertSame(0, StockMovement::where('type', StockMovementType::Sale)->count());

        $this->assertSame('INV-'.now()->year.'-00001', $this->sell([['product_id' => $a->id, 'quantity' => 1]])->invoice->invoice_number);
    }

    public function test_the_same_product_on_two_lines_cannot_oversell(): void
    {
        $p = $this->product(5);

        $this->expectException(InsufficientStockException::class);
        $this->sell([['product_id' => $p->id, 'quantity' => 3], ['product_id' => $p->id, 'quantity' => 3]]);
    }

    public function test_overselling_is_possible_only_when_the_company_allows_it(): void
    {
        $this->company->update(['settings' => ['allow_negative_stock' => true]]);
        app(CurrentCompany::class)->set($this->company->fresh());
        $p = $this->product(1);

        $this->sell([['product_id' => $p->id, 'quantity' => 3]]);

        $this->assertSame('-2.000', $p->fresh()->quantity);
    }

    public function test_invoice_numbers_are_sequential_unique_and_per_company(): void
    {
        $p = $this->product(50);
        $one = $this->sell([['product_id' => $p->id, 'quantity' => 1]]);
        $two = $this->sell([['product_id' => $p->id, 'quantity' => 1]]);

        $this->assertSame('INV-'.now()->year.'-00001', $one->invoice->invoice_number);
        $this->assertSame('INV-'.now()->year.'-00002', $two->invoice->invoice_number);

        $other = Company::factory()->create();
        $first = app(CurrentCompany::class)->runAs($other, function () {
            $product = Product::factory()->create();
            app(StockService::class)->add($product, 5);

            return $this->sell([['product_id' => $product->id, 'quantity' => 1]])->invoice->invoice_number;
        });
        $this->assertSame('INV-'.now()->year.'-00001', $first);
    }

    public function test_another_companys_product_or_customer_cannot_be_used(): void
    {
        $other = Company::factory()->create();
        [$foreignProduct, $foreignCustomer] = app(CurrentCompany::class)->runAs($other, fn () => [
            tap(Product::factory()->create(), fn ($p) => app(StockService::class)->add($p, 10)),
            Customer::factory()->create(),
        ]);

        try {
            $this->sell([['product_id' => $foreignProduct->id, 'quantity' => 1]]);
            $this->fail('foreign product accepted');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $own = $this->product();
        $this->expectException(ModelNotFoundException::class);
        $this->sell([['product_id' => $own->id, 'quantity' => 1]], ['customer_id' => $foreignCustomer->id]);
    }

    public function test_inactive_products_cannot_be_sold(): void
    {
        $p = $this->product();
        $p->update(['is_active' => false]);

        $this->expectException(BusinessRuleException::class);
        $this->sell([['product_id' => $p->id, 'quantity' => 1]]);
    }

    public function test_partial_payment_does_not_mark_the_invoice_paid(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 10]]); // 100.00

        $this->payments->record($sale, 40, 'cash');

        $sale->refresh();
        $this->assertSame('partial', $sale->payment_status);
        $this->assertSame('40.00', $sale->amount_paid);
        $this->assertSame('60.00', $sale->balance());

        $this->payments->record($sale, 60, 'bank_transfer', reference: 'TRX-1');
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->balance());
    }

    public function test_overpayment_zero_and_unknown_methods_are_refused(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]]); // 10.00

        foreach ([[10.01, 'cash'], [0, 'cash'], [-5, 'cash'], [5, 'bitcoin']] as [$amount, $method]) {
            try {
                $this->payments->record($sale, $amount, $method);
                $this->fail("accepted $amount via $method");
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Payment::count());
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_resubmitting_a_payment_with_the_same_key_does_not_duplicate_it(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 10]]);

        $first = $this->payments->record($sale, 30, 'cash', idempotencyKey: 'form-token-1');
        $second = $this->payments->record($sale, 30, 'cash', idempotencyKey: 'form-token-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::count());
        $this->assertSame('30.00', $sale->fresh()->amount_paid);
    }

    public function test_an_idempotency_key_cannot_be_reused_for_another_sale(): void
    {
        $p = $this->product(50);
        $a = $this->sell([['product_id' => $p->id, 'quantity' => 1]]);
        $b = $this->sell([['product_id' => $p->id, 'quantity' => 1]]);
        $this->payments->record($a, 5, 'cash', idempotencyKey: 'k');

        $this->expectException(BusinessRuleException::class);
        $this->payments->record($b, 5, 'cash', idempotencyKey: 'k');
    }

    public function test_voiding_a_payment_restores_the_balance_and_keeps_the_record(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 10]]);
        $payment = $this->payments->record($sale, 100, 'cash');
        $this->assertSame('paid', $sale->fresh()->payment_status);

        $this->payments->void($payment, 'Cheque bounced');

        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->amount_paid);
        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertSame(1, Payment::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.voided']);

        $this->expectException(BusinessRuleException::class);
        $this->payments->void($payment, 'again');
    }

    public function test_payment_can_be_taken_at_the_point_of_sale(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 2]], ['payment' => ['amount' => 20, 'method' => 'cash']]);

        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame(1, Payment::count());
    }

    public function test_cancelling_a_sale_restores_stock_with_a_reversing_movement(): void
    {
        $p = $this->product(10);
        $sale = $this->sell([['product_id' => $p->id, 'quantity' => 4]]);
        $this->assertSame('6.000', $p->fresh()->quantity);

        $this->sales->cancel($sale, 'Customer changed mind');

        $this->assertSame('10.000', $p->fresh()->quantity);
        $this->assertTrue($sale->fresh()->isCancelled());
        $this->assertSame(StockMovementType::SaleReversal, StockMovement::latest('id')->first()->type);
        $this->assertSame(1, Invoice::count());   // history is kept
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale.cancelled']);
    }

    public function test_a_sale_with_payments_cannot_be_cancelled_until_they_are_voided(): void
    {
        $p = $this->product(10);
        $sale = $this->sell([['product_id' => $p->id, 'quantity' => 1]]);
        $payment = $this->payments->record($sale, 5, 'cash');

        try {
            $this->sales->cancel($sale, 'oops');
            $this->fail('cancelled despite payments');
        } catch (BusinessRuleException) {
        }
        $this->assertSame('9.000', $p->fresh()->quantity);

        $this->payments->void($payment, 'refunded');
        $this->sales->cancel($sale, 'oops');
        $this->assertSame('10.000', $p->fresh()->quantity);
    }

    public function test_cancelled_sales_cannot_be_cancelled_again_or_paid_and_need_a_reason(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]]);

        try {
            $this->sales->cancel($sale, '  ');
            $this->fail('no reason accepted');
        } catch (BusinessRuleException) {
        }

        $this->sales->cancel($sale, 'mistake');

        foreach ([fn () => $this->sales->cancel($sale, 'again'), fn () => $this->payments->record($sale, 1, 'cash')] as $attempt) {
            try {
                $attempt();
                $this->fail('operation on cancelled sale allowed');
            } catch (BusinessRuleException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cancelling_works_even_if_the_product_was_archived_afterwards(): void
    {
        $p = $this->product(10);
        $sale = $this->sell([['product_id' => $p->id, 'quantity' => 2]]);
        $p->delete();

        $this->sales->cancel($sale, 'return');

        $this->assertSame('10.000', Product::withTrashed()->find($p->id)->quantity);
    }

    public function test_customer_outstanding_balance_and_overdue_tracking(): void
    {
        $customer = Customer::factory()->create();
        $p = $this->product(100, 10);
        $overdue = $this->sell([['product_id' => $p->id, 'quantity' => 10]], ['customer_id' => $customer->id, 'sale_date' => now()->subDays(60)->toDateString(), 'due_date' => now()->subDays(30)->toDateString()]);
        $current = $this->sell([['product_id' => $p->id, 'quantity' => 5]], ['customer_id' => $customer->id]);
        $cancelled = $this->sell([['product_id' => $p->id, 'quantity' => 1]], ['customer_id' => $customer->id]);
        $this->sales->cancel($cancelled, 'x');
        $this->payments->record($overdue, 30, 'cash');

        // 100 - 30 + 50, cancelled sale excluded
        $this->assertSame('120.00', $customer->outstandingBalance());
        $this->assertTrue($overdue->fresh()->isOverdue());
        $this->assertFalse($current->fresh()->isOverdue());
        $this->assertSame([$overdue->id], Sale::overdue()->pluck('id')->all());

        $this->payments->record($overdue, 70, 'cash');
        $this->assertSame(0, Sale::overdue()->count());
    }

    public function test_updating_details_changes_only_due_date_and_notes(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]], ['sale_date' => '2026-05-01']);

        $this->sales->updateDetails($sale, '2026-06-30', 'Net 60 agreed');

        $this->assertSame('2026-06-30', $sale->fresh()->due_date->toDateString());
        $this->assertSame('2026-06-30', $sale->invoice->fresh()->due_date->toDateString());
        $this->assertSame('10.00', $sale->fresh()->total);

        $this->expectException(BusinessRuleException::class);
        $this->sales->updateDetails($sale, '2026-04-01', null);
    }

    public function test_money_columns_cannot_be_mass_assigned(): void
    {
        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]]);

        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);
        $sale->update(['total' => '0.01']);
    }
}
