<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Sales\Create;
use App\Livewire\Sales\Index;
use App\Livewire\Sales\Show;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SalesPagesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    /** Livewire::test() has no HTTP pipeline, so set the tenant context the middleware would set. */
    private function actAs(string $role, ?Company $company = null): void
    {
        $company ??= $this->company;
        $this->actingAs($this->userWithRole($role, $company));
        app(CurrentCompany::class)->set($company);
    }

    private function inCompany(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    private function product(int $stock = 50, int $price = 10, ?Company $company = null): Product
    {
        return $this->inCompany($company ?? $this->company, function () use ($stock, $price) {
            $p = Product::factory()->create(['selling_price' => $price]);
            app(StockService::class)->add($p, $stock, StockMovementType::Purchase, 5);

            return $p;
        });
    }

    private function sale(Product $product, int $qty = 2, array $extra = [], ?Company $company = null): Sale
    {
        return $this->inCompany($company ?? $this->company, fn () => app(SaleService::class)->create(['items' => [['product_id' => $product->id, 'quantity' => $qty]]] + $extra));
    }

    public function test_sales_officer_completes_a_sale_through_the_page_and_stock_drops(): void
    {
        $product = $this->product(20, 10);
        $customer = $this->inCompany($this->company, fn () => Customer::factory()->create());
        $this->actAs('sales-officer');

        Livewire::test(Create::class)
            ->set('customerId', $customer->id)->call('addProduct', $product->id)
            ->set('lines.0.quantity', '3')->set('taxRate', '10')
            ->set('payNow', '10')->set('payMethod', 'cash')
            ->call('save')->assertHasNoErrors()->assertRedirect();

        $sale = $this->inCompany($this->company, fn () => Sale::with('invoice')->firstOrFail());
        $this->assertSame('33.00', $sale->total);              // 30 + 10% tax
        $this->assertSame('partial', $sale->payment_status);   // 10 of 33 paid
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('17.000', $this->inCompany($this->company, fn () => $product->fresh()->quantity));
    }

    public function test_page_requires_items_and_reports_insufficient_stock_without_saving(): void
    {
        $product = $this->product(2);
        $this->actAs('sales-officer');

        Livewire::test(Create::class)->call('save')->assertHasErrors('lines');
        Livewire::test(Create::class)->call('addProduct', $product->id)->set('lines.0.quantity', '5')
            ->call('save')->assertHasErrors('lines');

        $this->assertSame(0, $this->inCompany($this->company, fn () => Sale::count()));
        $this->assertSame('2.000', $this->inCompany($this->company, fn () => $product->fresh()->quantity));
    }

    public function test_invalid_discount_is_shown_as_an_error_and_saves_nothing(): void
    {
        $product = $this->product();
        $this->actAs('sales-officer');

        Livewire::test(Create::class)->call('addProduct', $product->id)
            ->set('discountType', 'fixed')->set('discountValue', '9999')
            ->call('save')->assertHasErrors('form');

        $this->assertSame(0, $this->inCompany($this->company, fn () => Sale::count()));
    }

    public function test_payment_field_is_ignored_for_users_without_the_record_payment_permission(): void
    {
        $product = $this->product();
        $this->actAs('inventory-officer');                    // can view inventory; give sales rights by hand below
        $role = \App\Models\Role::create(['name' => 'Seller only', 'slug' => 'seller-only']);
        $role->permissions()->attach(\App\Models\Permission::whereIn('slug', ['sales.view', 'sales.create', 'inventory.view'])->pluck('id'));
        $user = $this->userWithRole('seller-only', $this->company);
        $this->actingAs($user);

        Livewire::test(Create::class)->call('addProduct', $product->id)->set('payNow', '10')->call('save')->assertHasNoErrors();

        $this->assertSame(0, $this->inCompany($this->company, fn () => Payment::count()));
        $this->assertSame('unpaid', $this->inCompany($this->company, fn () => Sale::firstOrFail()->payment_status));
    }

    public function test_users_without_sales_permissions_are_blocked_at_the_route(): void
    {
        $sale = $this->sale($this->product());
        $this->actAs('hr-officer');

        $this->get('/sales')->assertForbidden();
        $this->get('/sales/create')->assertForbidden();
        $this->get("/sales/{$sale->id}")->assertForbidden();
        $this->get("/sales/{$sale->id}/invoice")->assertForbidden();
    }

    public function test_view_only_users_cannot_create_pay_or_cancel(): void
    {
        $sale = $this->sale($this->product());
        $this->actAs('accountant');   // views sales, records payments, but cannot create or cancel

        $this->get('/sales/create')->assertForbidden();
        Livewire::test(Show::class, ['sale' => $sale])->call('openCancel')->assertForbidden();
    }

    public function test_partial_then_full_payment_through_the_page_and_double_submit_is_harmless(): void
    {
        $sale = $this->sale($this->product(50, 10), 10);   // 100.00
        $this->actAs('sales-officer');

        $component = Livewire::test(Show::class, ['sale' => $sale])
            ->call('openPayment')->set('payAmount', '40')->set('payMethod', 'bank_transfer')
            ->call('recordPayment')->assertHasNoErrors();
        $this->assertSame('partial', $this->inCompany($this->company, fn () => $sale->fresh()->payment_status));

        // Overpayment is rejected with a message
        $component->call('openPayment')->set('payAmount', '70')->call('recordPayment')->assertHasErrors('payAmount');

        // Same modal submitted twice (same idempotency key) records the money once
        $component->set('payAmount', '60')->call('recordPayment')->call('recordPayment');
        $this->assertSame('paid', $this->inCompany($this->company, fn () => $sale->fresh()->payment_status));
        $this->assertSame(2, $this->inCompany($this->company, fn () => Payment::count()));
    }

    public function test_manager_can_void_a_payment_and_cancel_the_sale_which_restores_stock(): void
    {
        $product = $this->product(10, 10);
        $sale = $this->sale($product, 4);
        $this->actAs('manager');
        $this->actingAs($this->userWithRole('company-administrator', $this->company));

        $show = Livewire::test(Show::class, ['sale' => $sale])->call('openPayment')->set('payAmount', '40')->call('recordPayment');
        $show->call('openCancel')->set('cancelReason', 'Wrong customer')->call('cancelSale')->assertHasErrors('cancelReason'); // payment blocks it

        $paymentId = $this->inCompany($this->company, fn () => Payment::firstOrFail()->id);
        $show->call('openVoid', $paymentId)->set('voidReason', 'Refunded')->call('voidPayment')->assertHasNoErrors();
        $show->call('openCancel')->set('cancelReason', 'Wrong customer')->call('cancelSale')->assertHasNoErrors();

        $this->assertTrue($this->inCompany($this->company, fn () => $sale->fresh()->isCancelled()));
        $this->assertSame('10.000', $this->inCompany($this->company, fn () => $product->fresh()->quantity));
    }

    public function test_another_companys_sale_is_not_found_by_page_or_invoice_pdf(): void
    {
        $other = Company::factory()->create();
        $foreign = $this->sale($this->product(10, 10, $other), 1, [], $other);
        $this->actAs('company-administrator');

        $this->get("/sales/{$foreign->id}")->assertNotFound();
        $this->get("/sales/{$foreign->id}/invoice")->assertNotFound();
        $this->assertStringNotContainsString($foreign->invoice->invoice_number, Livewire::test(Index::class)->html());
    }

    public function test_invoice_pdf_is_generated_and_downloadable_for_own_sales(): void
    {
        $sale = $this->sale($this->product(10, 10), 2);
        $this->actAs('sales-officer');

        $inline = $this->get("/sales/{$sale->id}/invoice")->assertOk();
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $inline->getContent());

        $download = $this->get("/sales/{$sale->id}/invoice?download=1")->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString($sale->invoice->invoice_number, $download->headers->get('Content-Disposition'));
    }

    public function test_sales_list_filters_by_status_payment_date_customer_and_search(): void
    {
        $p = $this->product(100, 10);
        $customer = $this->inCompany($this->company, fn () => Customer::factory()->create(['name' => 'Zed Stores']));
        $overdue = $this->sale($p, 1, ['customer_id' => $customer->id, 'sale_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(10)->toDateString()]);
        $paid = $this->sale($p, 1, ['payment' => ['amount' => 10, 'method' => 'cash']]);
        $cancelled = $this->sale($p, 1);
        $this->inCompany($this->company, fn () => app(SaleService::class)->cancel($cancelled, 'x'));
        $this->actAs('manager');

        $inv = fn (Sale $s) => $s->invoice->invoice_number;
        $page = fn () => Livewire::test(Index::class);

        $page()->set('payment', 'overdue')->assertSee($inv($overdue))->assertDontSee($inv($paid))->assertDontSee($inv($cancelled));
        $page()->set('payment', 'paid')->assertSee($inv($paid))->assertDontSee($inv($overdue));
        $page()->set('status', 'cancelled')->assertSee($inv($cancelled))->assertDontSee($inv($paid));
        $page()->set('customer', $customer->id)->assertSee($inv($overdue))->assertDontSee($inv($paid));
        $page()->set('search', 'Zed')->assertSee($inv($overdue))->assertDontSee($inv($paid));
        $page()->set('search', $inv($paid))->assertSee($inv($paid))->assertDontSee($inv($overdue));
        $page()->set('from', now()->subDays(5)->toDateString())->assertSee($inv($paid))->assertDontSee($inv($overdue));
        $page()->set('to', now()->subDays(20)->toDateString())->assertSee($inv($overdue))->assertDontSee($inv($paid));
    }

    public function test_customer_crud_profile_and_isolation(): void
    {
        $other = Company::factory()->create();
        $foreign = $this->inCompany($other, fn () => Customer::factory()->create(['name' => 'Secret Customer']));
        $this->actAs('sales-officer');

        Livewire::test(CustomersIndex::class)
            ->set('form.name', 'Mwangi Traders')->set('form.email', 'mw@example.test')->call('save')->assertHasNoErrors()
            ->assertSee('Mwangi Traders')->assertDontSee('Secret Customer')
            ->call('create')->set('form.name', '')->call('save')->assertHasErrors('form.name');

        $mine = $this->inCompany($this->company, fn () => Customer::where('name', 'Mwangi Traders')->firstOrFail());
        $this->assertSame($this->company->id, $mine->company_id);
        $this->get("/customers/{$mine->id}")->assertOk()->assertSee('Mwangi Traders');
        $this->get("/customers/{$foreign->id}")->assertNotFound();

        Livewire::test(CustomersIndex::class)->call('edit', $foreign->id)->assertNotFound();
    }

    public function test_customer_profile_shows_balance_and_history(): void
    {
        $customer = $this->inCompany($this->company, fn () => Customer::factory()->create());
        $p = $this->product(20, 10);
        $this->sale($p, 5, ['customer_id' => $customer->id]);                                    // 50 owed
        $this->sale($p, 2, ['customer_id' => $customer->id, 'payment' => ['amount' => 20, 'method' => 'cash']]); // paid
        $this->actAs('sales-officer');

        $this->get("/customers/{$customer->id}")->assertOk()->assertSee('50.00')->assertSee('INV-');
    }

    public function test_only_users_with_manage_permission_can_change_customers_and_suppliers(): void
    {
        $this->actAs('inventory-officer');
        $this->get('/customers')->assertForbidden();   // no customers.view

        $this->actAs('accountant');                    // customers.view and suppliers.view, no manage
        Livewire::test(CustomersIndex::class)->assertOk()->call('create')->assertForbidden();
        Livewire::test(SuppliersIndex::class)->assertOk()->call('create')->assertForbidden();
    }

    public function test_supplier_crud_archive_restore_and_isolation(): void
    {
        $other = Company::factory()->create();
        $this->inCompany($other, fn () => Supplier::factory()->create(['name' => 'Rival Supplier']));
        $this->actAs('procurement-officer');

        $page = Livewire::test(SuppliersIndex::class)
            ->set('form.name', 'Steel Works Ltd')->set('form.phone', '0700000000')->call('save')->assertHasNoErrors()
            ->assertSee('Steel Works Ltd')->assertDontSee('Rival Supplier');

        $supplier = $this->inCompany($this->company, fn () => Supplier::firstOrFail());
        $page->call('archive', $supplier->id)->assertDontSee('Steel Works Ltd')
            ->set('view', 'archived')->assertSee('Steel Works Ltd')->call('restore', $supplier->id)
            ->set('view', 'active')->assertSee('Steel Works Ltd');
    }
}
