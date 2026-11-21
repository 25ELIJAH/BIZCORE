<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierPaymentService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProcurementServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private PurchaseOrderService $orders;

    private GoodsReceiptService $receipts;

    private SupplierPaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
        $this->orders = app(PurchaseOrderService::class);
        $this->receipts = app(GoodsReceiptService::class);
        $this->payments = app(SupplierPaymentService::class);
    }

    private function draft(array $items = null, array $extra = []): PurchaseOrder
    {
        $items ??= [['product_id' => Product::factory()->create()->id, 'quantity' => 10, 'unit_cost' => 5]];

        return $this->orders->saveDraft(['supplier_id' => Supplier::factory()->create()->id, 'items' => $items] + $extra);
    }

    private function approved(array $items = null, array $extra = []): PurchaseOrder
    {
        $order = $this->draft($items, $extra);
        $this->orders->submit($order);
        $this->orders->approve($order);

        return $order;
    }

    public function test_draft_calculates_totals_and_does_not_touch_stock(): void
    {
        $product = Product::factory()->create();
        $order = $this->draft([['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 12.50]], ['tax_rate' => 16]);

        $this->assertSame('50.00', $order->subtotal);
        $this->assertSame('8.00', $order->tax_amount);
        $this->assertSame('58.00', $order->total);
        $this->assertSame('draft', $order->status);
        $this->assertStringStartsWith('PO-', $order->po_number);
        $this->assertSame('0.000', (string) $product->refresh()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_approval_flow_and_approval_does_not_change_stock(): void
    {
        $order = $this->approved();

        $this->assertSame('approved', $order->status);
        $this->assertNotNull($order->approved_at);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_cannot_skip_states(): void
    {
        $order = $this->draft();

        $this->expectException(BusinessRuleException::class);
        $this->orders->approve($order);
    }

    public function test_rejection_returns_to_draft_with_reason_and_allows_editing(): void
    {
        $order = $this->draft();
        $this->orders->submit($order);
        $this->orders->reject($order, 'Price too high');

        $this->assertSame('draft', $order->status);
        $this->assertSame('Price too high', $order->rejection_reason);

        $product = Product::factory()->create();
        $edited = $this->orders->saveDraft(['supplier_id' => $order->supplier_id, 'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 3]]], $order);
        $this->assertSame('3.00', $edited->total);
        $this->assertCount(1, $edited->items);
        $this->assertNull($edited->rejection_reason);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $order = $this->draft();
        $this->orders->submit($order);

        $this->expectException(BusinessRuleException::class);
        $this->orders->reject($order, '  ');
    }

    public function test_only_drafts_can_be_edited(): void
    {
        $order = $this->approved();

        $this->expectException(BusinessRuleException::class);
        $this->orders->saveDraft(['supplier_id' => $order->supplier_id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1]]], $order);
    }

    public function test_empty_order_and_bad_quantities_rejected(): void
    {
        foreach ([[], [['product_id' => Product::factory()->create()->id, 'quantity' => 0]]] as $items) {
            try {
                $this->orders->saveDraft(['supplier_id' => Supplier::factory()->create()->id, 'items' => $items]);
                $this->fail('Expected BusinessRuleException');
            } catch (BusinessRuleException) {
                $this->assertSame(0, PurchaseOrder::count());
            }
        }
    }

    public function test_full_receipt_increases_stock_with_purchase_movement_and_average_cost(): void
    {
        $product = Product::factory()->create(['purchase_price' => 5]);
        $order = $this->approved([['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5]]);
        $item = $order->items->first();

        $receipt = $this->receipts->receive($order, [['purchase_order_item_id' => $item->id, 'quantity' => 10]]);

        $this->assertSame('received', $order->status);
        $this->assertSame('10.000', (string) $product->refresh()->quantity);
        $this->assertSame('5.0000', (string) $product->average_cost);
        $movement = StockMovement::first();
        $this->assertSame('purchase', $movement->type->value);
        $this->assertStringStartsWith('GRN-', $receipt->receipt_number);
    }

    public function test_partial_receipts_track_outstanding_and_complete_the_order(): void
    {
        $product = Product::factory()->create();
        $order = $this->approved([['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 4]]);
        $item = $order->items->first();

        $this->receipts->receive($order, [['purchase_order_item_id' => $item->id, 'quantity' => 4]]);
        $this->assertSame('partially_received', $order->status);
        $this->assertSame('6.000', $item->refresh()->quantityOutstanding());
        $this->assertSame('4.000', (string) $product->refresh()->quantity);

        $this->receipts->receive($order, [['purchase_order_item_id' => $item->id, 'quantity' => 6]]);
        $this->assertSame('received', $order->status);
        $this->assertSame('10.000', (string) $product->refresh()->quantity);
        $this->assertSame(2, $order->receipts()->count());
    }

    public function test_cannot_over_receive_and_failure_changes_nothing(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $order = $this->approved([
            ['product_id' => $a->id, 'quantity' => 5, 'unit_cost' => 1],
            ['product_id' => $b->id, 'quantity' => 5, 'unit_cost' => 1],
        ]);
        [$i1, $i2] = $order->items;

        try {
            $this->receipts->receive($order, [
                ['purchase_order_item_id' => $i1->id, 'quantity' => 5],
                ['purchase_order_item_id' => $i2->id, 'quantity' => 6],
            ]);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->assertSame('0.000', (string) $a->refresh()->quantity);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, $order->receipts()->count());
        $this->assertSame('0.000', (string) $i1->refresh()->quantity_received);
    }

    public function test_unapproved_orders_cannot_receive_goods(): void
    {
        $order = $this->draft();

        $this->expectException(BusinessRuleException::class);
        $this->receipts->receive($order, [['purchase_order_item_id' => $order->items->first()->id, 'quantity' => 1]]);
    }

    public function test_item_from_another_order_cannot_be_received(): void
    {
        $order = $this->approved();
        $other = $this->approved();

        $this->expectException(BusinessRuleException::class);
        $this->receipts->receive($order, [['purchase_order_item_id' => $other->items->first()->id, 'quantity' => 1]]);
    }

    public function test_cancel_rules(): void
    {
        $order = $this->approved();
        $this->orders->cancel($order, 'Supplier withdrew');
        $this->assertSame('cancelled', $order->status);

        $received = $this->approved();
        $this->receipts->receive($received, [['purchase_order_item_id' => $received->items->first()->id, 'quantity' => 1]]);
        $this->expectException(BusinessRuleException::class);
        $this->orders->cancel($received, 'Too late');
    }

    public function test_cannot_cancel_with_active_payments(): void
    {
        $order = $this->approved();
        $this->payments->record($order, 10, 'cash');

        $this->expectException(BusinessRuleException::class);
        $this->orders->cancel($order, 'Changed mind');
    }

    public function test_supplier_payments_update_balance_and_cannot_exceed_it(): void
    {
        $order = $this->approved([['product_id' => Product::factory()->create()->id, 'quantity' => 10, 'unit_cost' => 10]]);
        $this->assertSame('100.00', $order->balance());

        $this->payments->record($order, 30, 'cash');
        $this->assertSame('70.00', $order->balance());
        $this->assertSame('70.00', $order->supplier->outstandingBalance());

        try {
            $this->payments->record($order, 70.01, 'cash');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->payments->record($order, 70, 'cash');
        $this->assertSame('0.00', $order->balance());
    }

    public function test_payment_rejected_for_draft_order_and_bad_method(): void
    {
        $draft = $this->draft();
        try {
            $this->payments->record($draft, 1, 'cash');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $order = $this->approved();
        $this->expectException(BusinessRuleException::class);
        $this->payments->record($order, 1, 'barter');
    }

    public function test_payment_idempotency_key_prevents_duplicates(): void
    {
        $order = $this->approved();
        $first = $this->payments->record($order, 10, 'cash', null, null, 'key-1');
        $second = $this->payments->record($order, 10, 'cash', null, null, 'key-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('10.00', (string) $order->refresh()->amount_paid);
    }

    public function test_voiding_payment_restores_balance_and_requires_reason(): void
    {
        $order = $this->approved();
        $payment = $this->payments->record($order, 20, 'cash');

        try {
            $this->payments->void($payment, '');
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        $this->payments->void($payment, 'Entered in error');
        $this->assertSame('0.00', (string) $order->refresh()->amount_paid);
        $this->assertTrue($payment->refresh()->isVoided());

        $this->expectException(BusinessRuleException::class);
        $this->payments->void($payment, 'Again');
    }

    public function test_orders_are_isolated_between_companies(): void
    {
        $order = $this->approved();
        $itemId = $order->items->first()->id;

        $other = Company::factory()->create();
        app(CurrentCompany::class)->set($other);

        $this->assertSame(0, PurchaseOrder::count());
        try {
            $this->orders->submit($order);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }
        try {
            $this->receipts->receive($order, [['purchase_order_item_id' => $itemId, 'quantity' => 1]]);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }
        try {
            $this->payments->record($order, 1, 'cash');
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }
    }

    public function test_cannot_order_from_another_companys_supplier(): void
    {
        $foreign = Company::factory()->create();
        $supplier = app(CurrentCompany::class)->runAs($foreign, fn () => Supplier::factory()->create());

        $this->expectException(ModelNotFoundException::class);
        $this->orders->saveDraft(['supplier_id' => $supplier->id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 1]]]);
    }

    public function test_numbers_are_sequential_per_company(): void
    {
        $a = $this->draft();
        $b = $this->draft();
        $this->assertNotSame($a->po_number, $b->po_number);

        $other = Company::factory()->create();
        $first = app(CurrentCompany::class)->runAs($other, function () use ($other) {
            $this->actingAs($this->userWithRole('company-administrator', $other));

            return $this->draft();
        });
        $this->assertSame(substr($a->po_number, 0, -5).'00001', $first->po_number);
    }
}
