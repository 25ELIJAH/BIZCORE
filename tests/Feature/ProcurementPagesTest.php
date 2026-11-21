<?php

namespace Tests\Feature;

use App\Livewire\Purchasing\Form;
use App\Livewire\Purchasing\Index;
use App\Livewire\Purchasing\Show;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProcurementPagesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    private function actAs(string $role, ?Company $company = null): void
    {
        $company ??= $this->company;
        $this->actingAs($this->userWithRole($role, $company));
        app(CurrentCompany::class)->set($company);
    }

    /** Build an order as the company admin, optionally advancing it to a status. */
    private function order(string $status = 'draft', ?Company $company = null): PurchaseOrder
    {
        $company ??= $this->company;

        return app(CurrentCompany::class)->runAs($company, function () use ($status) {
            $this->actingAs($this->userWithRole('company-administrator', $this->company));
            $svc = app(PurchaseOrderService::class);
            $order = $svc->saveDraft([
                'supplier_id' => Supplier::factory()->create()->id,
                'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 10, 'unit_cost' => 5]],
            ]);
            if ($status !== 'draft') {
                $svc->submit($order);
            }
            if (in_array($status, ['approved'], true)) {
                $svc->approve($order);
            }

            return $order;
        });
    }

    public function test_procurement_officer_creates_a_draft_through_the_page(): void
    {
        $supplier = app(CurrentCompany::class)->runAs($this->company, fn () => Supplier::factory()->create());
        $product = app(CurrentCompany::class)->runAs($this->company, fn () => Product::factory()->create(['purchase_price' => 8]));
        $this->actAs('procurement-officer');

        Livewire::test(Form::class)
            ->set('supplierId', $supplier->id)->call('addProduct', $product->id)
            ->set('lines.0.quantity', '5')->set('taxRate', '10')
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $order = PurchaseOrder::first();
        $this->assertSame('draft', $order->status);
        $this->assertSame('40.00', $order->subtotal);
        $this->assertSame('44.00', $order->total);
    }

    public function test_form_validates_supplier_and_items(): void
    {
        $this->actAs('procurement-officer');

        Livewire::test(Form::class)->call('save')->assertHasErrors(['supplierId', 'lines']);
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_cannot_pick_a_supplier_from_another_company(): void
    {
        $other = Company::factory()->create();
        $foreign = app(CurrentCompany::class)->runAs($other, fn () => Supplier::factory()->create());
        $product = app(CurrentCompany::class)->runAs($this->company, fn () => Product::factory()->create());
        $this->actAs('procurement-officer');

        Livewire::test(Form::class)->set('supplierId', $foreign->id)->call('addProduct', $product->id)
            ->call('save')->assertHasErrors(['supplierId']);
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_officer_can_submit_but_not_approve_and_manager_can_approve(): void
    {
        $order = $this->order('draft');

        $this->actAs('procurement-officer');
        Livewire::test(Show::class, ['purchaseOrder' => $order])->call('submit')->assertHasNoErrors();
        $this->assertSame('pending_approval', $order->refresh()->status);

        Livewire::test(Show::class, ['purchaseOrder' => $order])->call('approve')->assertForbidden();
        $this->assertSame('pending_approval', $order->refresh()->status);

        $this->actAs('manager');
        Livewire::test(Show::class, ['purchaseOrder' => $order])->call('approve');
        $this->assertSame('approved', $order->refresh()->status);
    }

    public function test_manager_rejects_with_reason(): void
    {
        $order = $this->order('pending');
        $this->actAs('manager');

        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('openReason', 'reject')->set('reason', '')->call('submitReason')->assertHasErrors(['reason'])
            ->set('reason', 'Check prices')->call('submitReason')->assertHasNoErrors();

        $this->assertSame('draft', $order->refresh()->status);
        $this->assertSame('Check prices', $order->rejection_reason);
    }

    public function test_receive_flow(): void
    {
        $order = $this->order('approved');
        $item = $order->items->first();
        $this->actAs('procurement-officer');

        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceive')
            ->set("receiveQty.{$item->id}", '4')->call('receive')->assertHasNoErrors();

        $this->assertSame('partially_received', $order->refresh()->status);
        $this->assertSame('4.000', (string) app(CurrentCompany::class)->runAs($this->company, fn () => Product::find($item->product_id))->quantity);

        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceive')->set("receiveQty.{$item->id}", '7')->call('receive')->assertHasErrors(['receiveQty']);
        $this->assertSame('partially_received', $order->refresh()->status);
    }

    public function test_officer_cannot_record_supplier_payments_but_accountant_can(): void
    {
        $order = $this->order('approved');

        $this->actAs('procurement-officer');
        Livewire::test(Show::class, ['purchaseOrder' => $order])->call('openPayment')->assertForbidden();

        $this->actAs('accountant');
        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('openPayment')->set('payAmount', '20')->call('recordPayment')->assertHasNoErrors();
        $this->assertSame('20.00', (string) $order->refresh()->amount_paid);

        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('openPayment')->set('payAmount', '999')->call('recordPayment')->assertHasErrors(['payAmount']);
        $this->assertSame('20.00', (string) $order->refresh()->amount_paid);
    }

    public function test_other_company_users_get_404_for_the_order_and_supplier_pages(): void
    {
        $order = $this->order('draft');
        $other = Company::factory()->create();
        $user = $this->userWithRole('company-administrator', $other);

        $this->actingAs($user)->get(route('purchase-orders.show', $order->getKey()))->assertNotFound();
        $this->get(route('purchase-orders.edit', $order->getKey()))->assertNotFound();
        $this->get(route('suppliers.show', $order->supplier_id))->assertNotFound();
    }

    public function test_viewers_without_permission_are_forbidden(): void
    {
        $this->actAs('employee');
        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $draft = $this->order('draft');
        $approved = $this->order('approved');
        $this->actAs('manager');

        Livewire::test(Index::class)->assertSee($draft->po_number)->assertSee($approved->po_number)
            ->set('status', 'approved')->assertSee($approved->po_number)->assertDontSee($draft->po_number)
            ->set('status', 'all')->set('search', $draft->po_number)->assertSee($draft->po_number)->assertDontSee($approved->po_number);
    }

    public function test_supplier_profile_shows_balance_and_history(): void
    {
        $order = $this->order('approved');
        $this->actAs('accountant');

        Livewire::test(SupplierShow::class, ['supplier' => $order->supplier])
            ->assertSee($order->po_number)->assertSee('50.00');
    }

    public function test_approved_orders_cannot_be_edited_through_the_page(): void
    {
        $order = $this->order('approved');
        $this->actAs('procurement-officer');

        Livewire::test(Form::class, ['purchaseOrder' => $order])->assertForbidden();
    }
}
