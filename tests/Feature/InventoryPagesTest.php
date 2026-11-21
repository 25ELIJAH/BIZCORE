<?php

namespace Tests\Feature;

use App\Livewire\Inventory\Categories;
use App\Livewire\Inventory\Products;
use App\Livewire\Inventory\StockMovements;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InventoryPagesTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
    }

    /** Livewire::test() has no HTTP pipeline, so set the tenant context the middleware would set. */
    private function actAs(string $role): void
    {
        $this->actingAs($this->userWithRole($role, $this->company));
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_company_middleware_is_persistent_for_livewire_update_requests(): void
    {
        $this->assertContains(\App\Http\Middleware\ResolveCurrentCompany::class, Livewire::getPersistentMiddleware());
    }

    private function inCompany(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    public function test_officer_can_create_a_product_with_opening_stock_through_the_page(): void
    {
        $this->actAs('inventory-officer');
        $category = $this->inCompany($this->company, fn () => ProductCategory::factory()->create());

        Livewire::test(Products::class)
            ->set('form.sku', 'BOLT-10')->set('form.name', 'Bolt M10')->set('form.product_category_id', $category->id)
            ->set('form.purchase_price', '2.50')->set('form.selling_price', '4')->set('form.min_stock', '20')
            ->set('form.opening_quantity', '100')
            ->call('save')->assertHasNoErrors()->assertSee('Product created.');

        $product = $this->inCompany($this->company, fn () => Product::where('sku', 'BOLT-10')->firstOrFail());
        $this->assertSame($this->company->id, $product->company_id);
        $this->assertSame('100.000', $product->quantity);
        $this->assertSame(1, $this->inCompany($this->company, fn () => StockMovement::where('product_id', $product->id)->count()));
    }

    public function test_sku_must_be_unique_within_the_company_but_may_repeat_across_companies(): void
    {
        $other = Company::factory()->create();
        $this->inCompany($other, fn () => Product::factory()->create(['sku' => 'SHARED-1']));
        $this->inCompany($this->company, fn () => Product::factory()->create(['sku' => 'DUP-1']));
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)->set('form.sku', 'DUP-1')->set('form.name', 'Dup')->call('save')->assertHasErrors('form.sku');
        Livewire::test(Products::class)->set('form.sku', 'SHARED-1')->set('form.name', 'Ok')->call('save')->assertHasNoErrors();
    }

    public function test_a_category_from_another_company_is_rejected(): void
    {
        $foreignCategory = $this->inCompany(Company::factory()->create(), fn () => ProductCategory::factory()->create());
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)
            ->set('form.sku', 'X1')->set('form.name', 'X')->set('form.product_category_id', $foreignCategory->id)
            ->call('save')->assertHasErrors('form.product_category_id');
    }

    public function test_product_list_never_shows_another_companys_products(): void
    {
        $this->inCompany($this->company, fn () => Product::factory()->create(['name' => 'Mine Widget']));
        $this->inCompany(Company::factory()->create(), fn () => Product::factory()->create(['name' => 'Their Secret Widget']));
        $this->actAs('inventory-officer');

        $this->get('/inventory/products')->assertOk()->assertSee('Mine Widget')->assertDontSee('Their Secret Widget');
    }

    public function test_editing_another_companys_product_id_fails(): void
    {
        $foreign = $this->inCompany(Company::factory()->create(), fn () => Product::factory()->create());
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)->call('edit', $foreign->id)->assertStatus(404);
    }

    public function test_users_without_manage_permission_cannot_change_products_even_by_calling_actions(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::factory()->create());
        $this->actAs('sales-officer'); // has inventory.view only

        Livewire::test(Products::class)->assertOk()->call('create')->assertForbidden();
        Livewire::test(Products::class)->call('archive', $product->id)->assertForbidden();
        Livewire::test(Products::class)->call('openStock', $product->id, 'add')->assertForbidden();

        $this->assertNotNull($this->inCompany($this->company, fn () => Product::find($product->id)));
    }

    public function test_users_without_inventory_view_cannot_open_inventory_pages(): void
    {
        $this->actAs('hr-officer');

        $this->get('/inventory/products')->assertForbidden();
        $this->get('/inventory/movements')->assertForbidden();
    }

    public function test_stock_can_be_added_deducted_and_counted_from_the_page(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::factory()->create());
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)
            ->call('openStock', $product->id, 'add')->set('stockQuantity', '10')->call('applyStock')->assertHasNoErrors()
            ->call('openStock', $product->id, 'deduct')->set('stockQuantity', '4')->call('applyStock')->assertHasNoErrors()
            ->call('openStock', $product->id, 'count')->set('stockQuantity', '5')->set('stockReason', 'Recount')->call('applyStock')->assertHasNoErrors();

        $this->assertSame('5.000', $this->inCompany($this->company, fn () => $product->fresh()->quantity));
        $this->assertSame(3, $this->inCompany($this->company, fn () => StockMovement::count()));
    }

    public function test_deducting_more_than_available_shows_an_error_and_changes_nothing(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::factory()->create());
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)
            ->call('openStock', $product->id, 'deduct')->set('stockQuantity', '3')->call('applyStock')
            ->assertHasErrors('stockQuantity');

        $this->assertSame('0.000', $this->inCompany($this->company, fn () => $product->fresh()->quantity));
    }

    public function test_stock_count_requires_a_reason(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::factory()->create());
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)
            ->call('openStock', $product->id, 'count')->set('stockQuantity', '3')->call('applyStock')
            ->assertHasErrors('stockReason');
    }

    public function test_low_stock_filter_and_search_work(): void
    {
        $this->inCompany($this->company, function () {
            $low = Product::factory()->create(['name' => 'Alpha Low', 'min_stock' => 10]);
            $fine = Product::factory()->create(['name' => 'Beta Fine', 'min_stock' => 1]);
            app(\App\Services\StockService::class)->add($low, 2);
            app(\App\Services\StockService::class)->add($fine, 50);
        });
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)->set('view', 'low')->assertSee('Alpha Low')->assertDontSee('Beta Fine')
            ->set('view', 'active')->set('search', 'Beta')->assertSee('Beta Fine')->assertDontSee('Alpha Low');
    }

    public function test_archived_products_are_hidden_but_restorable(): void
    {
        $product = $this->inCompany($this->company, fn () => Product::factory()->create(['name' => 'Retired Gadget']));
        $this->actAs('inventory-officer');

        Livewire::test(Products::class)->call('archive', $product->id)->assertDontSee('Retired Gadget')
            ->set('view', 'archived')->assertSee('Retired Gadget')
            ->call('restore', $product->id)->set('view', 'active')->assertSee('Retired Gadget');
    }

    public function test_categories_cannot_be_deleted_while_in_use_and_names_are_unique_per_company(): void
    {
        $category = $this->inCompany($this->company, fn () => ProductCategory::factory()->create(['name' => 'Tools']));
        $this->inCompany($this->company, fn () => Product::factory()->create(['product_category_id' => $category->id]));
        $this->actAs('inventory-officer');

        Livewire::test(Categories::class)->call('delete', $category->id)->assertSee('Move or archive them first');
        $this->assertNotNull($this->inCompany($this->company, fn () => ProductCategory::find($category->id)));

        Livewire::test(Categories::class)->set('name', 'Tools')->call('save')->assertHasErrors('name');
        Livewire::test(Categories::class)->set('name', 'Paint')->call('save')->assertHasNoErrors();
    }

    public function test_movement_history_is_scoped_filterable_and_company_isolated(): void
    {
        $mine = $this->inCompany($this->company, fn () => Product::factory()->create(['name' => 'Mine']));
        $this->inCompany($this->company, fn () => app(\App\Services\StockService::class)->add($mine, 5, reason: 'my delivery'));
        $other = Company::factory()->create();
        $theirs = $this->inCompany($other, fn () => Product::factory()->create(['name' => 'Theirs']));
        $this->inCompany($other, fn () => app(\App\Services\StockService::class)->add($theirs, 9, reason: 'their delivery'));
        $this->actAs('inventory-officer');

        Livewire::test(StockMovements::class)->assertSee('my delivery')->assertDontSee('their delivery')
            ->set('type', 'sale')->assertDontSee('my delivery')
            ->set('type', 'addition')->assertSee('my delivery');
    }
}
