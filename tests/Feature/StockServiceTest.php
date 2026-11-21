<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ProductService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $user = $this->userWithRole('inventory-officer', $this->company);
        $this->actingAs($user);
        app(CurrentCompany::class)->set($this->company);
        $this->stock = app(StockService::class);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    public function test_adding_stock_records_a_complete_movement(): void
    {
        $product = $this->product();

        $movement = $this->stock->add($product, 10, StockMovementType::Addition, 4, reason: 'Delivery');

        $this->assertSame('10.000', $product->fresh()->quantity);
        $this->assertSame('0.000', $movement->previous_quantity);
        $this->assertSame('10.000', $movement->new_quantity);
        $this->assertSame('10.000', $movement->quantity);
        $this->assertSame($this->company->id, $movement->company_id);
        $this->assertSame(auth()->id(), $movement->user_id);
        $this->assertSame(StockMovementType::Addition, $movement->type);
        $this->assertNotNull($movement->fresh()->created_at);
    }

    public function test_deducting_stock_reduces_quantity_and_records_a_negative_movement(): void
    {
        $product = $this->product();
        $this->stock->add($product, 10);

        $movement = $this->stock->deduct($product, 3.5);

        $this->assertSame('6.500', $product->fresh()->quantity);
        $this->assertSame('-3.500', $movement->quantity);
        $this->assertSame('10.000', $movement->previous_quantity);
        $this->assertSame('6.500', $movement->new_quantity);
    }

    public function test_cannot_deduct_more_than_available_and_nothing_changes(): void
    {
        $product = $this->product();
        $this->stock->add($product, 5);

        try {
            $this->stock->deduct($product, 6, StockMovementType::Sale);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame('5.000', $e->available);
        }

        $this->assertSame('5.000', $product->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
    }

    public function test_negative_stock_is_allowed_only_when_the_company_setting_enables_it(): void
    {
        $product = $this->product();
        $this->company->update(['settings' => ['allow_negative_stock' => true]]);
        app(CurrentCompany::class)->set($this->company->fresh());

        $this->stock->deduct($product, 2, StockMovementType::Sale);

        $this->assertSame('-2.000', $product->fresh()->quantity);
    }

    public function test_zero_or_negative_quantities_are_rejected(): void
    {
        $product = $this->product();

        $this->expectException(InvalidArgumentException::class);
        $this->stock->add($product, 0);
    }

    public function test_wrong_direction_for_the_movement_type_is_rejected(): void
    {
        $product = $this->product();
        $this->stock->add($product, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->deduct($product, 1, StockMovementType::Purchase);
    }

    public function test_adjustment_sets_the_counted_quantity_and_is_audited(): void
    {
        $product = $this->product();
        $this->stock->add($product, 10);

        $movement = $this->stock->adjustTo($product, 7, 'Stock-take: 3 damaged');

        $this->assertSame(StockMovementType::AdjustmentOut, $movement->type);
        $this->assertSame('-3.000', $movement->quantity);
        $this->assertSame('7.000', $product->fresh()->quantity);
        $this->assertSame('Stock-take: 3 damaged', $movement->reason);
        $log = \App\Models\ActivityLog::where('action', 'stock.adjusted')->firstOrFail();
        $this->assertSame($this->company->id, $log->company_id);
        $this->assertSame('7.000', $log->properties['new']['quantity']);
        $this->assertSame('10.000', $log->properties['old']['quantity']);
    }

    public function test_adjustment_upwards_and_no_op_when_count_matches(): void
    {
        $product = $this->product();
        $this->stock->add($product, 10);

        $this->assertSame(StockMovementType::AdjustmentIn, $this->stock->adjustTo($product, 12, 'Found extra')->type);
        $this->assertNull($this->stock->adjustTo($product, 12, 'Recount'));
        $this->assertSame(2, StockMovement::where('product_id', $product->id)->count());
    }

    public function test_adjustment_requires_a_reason(): void
    {
        $product = $this->product();
        $this->stock->add($product, 10);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->adjustTo($product, 5, '   ');
    }

    public function test_weighted_average_cost_and_valuation(): void
    {
        $product = $this->product();

        $this->stock->add($product, 10, StockMovementType::Purchase, 4);   // 10 @ 4
        $this->stock->add($product, 30, StockMovementType::Purchase, 8);   // 30 @ 8 => avg 7

        $product->refresh();
        $this->assertSame('7.0000', $product->average_cost);
        $this->assertSame('280.00', $product->stockValue());

        $this->stock->deduct($product, 10, StockMovementType::Sale);        // cost unchanged on sale
        $this->assertSame('7.0000', $product->fresh()->average_cost);
        $this->assertSame('210.00', $product->fresh()->stockValue()); // 30 left × 7.00
    }

    public function test_another_companys_product_cannot_be_touched(): void
    {
        $other = Company::factory()->create();
        $foreign = app(CurrentCompany::class)->runAs($other, fn () => $this->product());

        $this->expectException(ModelNotFoundException::class);
        $this->stock->add($foreign, 5);
    }

    public function test_low_stock_flag_uses_the_configured_minimum(): void
    {
        $low = $this->product(['min_stock' => 5]);
        $ok = $this->product(['min_stock' => 5]);
        $noMin = $this->product(['min_stock' => 0]);
        $this->stock->add($low, 5);   // equals minimum => low
        $this->stock->add($ok, 6);

        $this->assertTrue($low->fresh()->isLowStock());
        $this->assertFalse($ok->fresh()->isLowStock());
        $this->assertFalse($noMin->fresh()->isLowStock());
        $this->assertSame([$low->id], Product::lowStock()->pluck('id')->all());
    }

    public function test_creating_a_product_with_opening_stock_records_an_opening_movement(): void
    {
        $product = app(ProductService::class)->create(
            ['sku' => 'WID-1', 'name' => 'Widget', 'purchase_price' => 12, 'selling_price' => 20, 'min_stock' => 2],
            25,
        );

        $this->assertSame('25.000', $product->quantity);
        $this->assertSame('12.0000', $product->average_cost);
        $this->assertSame(StockMovementType::Opening, $product->stockMovements()->firstOrFail()->type);
    }

    public function test_archiving_keeps_stock_history(): void
    {
        $product = app(ProductService::class)->create(['sku' => 'OLD-1', 'name' => 'Old', 'purchase_price' => 1, 'selling_price' => 2], 3);

        app(ProductService::class)->archive($product);

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
    }

    public function test_quantity_is_not_mass_assignable(): void
    {
        $product = $this->product();
        $product->update(['quantity' => 999]);

        $this->assertSame('0.000', $product->fresh()->quantity);
    }
}
