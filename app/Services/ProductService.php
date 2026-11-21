<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private StockService $stock, private ActivityLogger $activityLogger) {}

    /**
     * @param  array<string, mixed>  $data  Product attributes (quantity is not one of them)
     * @param  string|int|float  $openingQuantity  Starting stock, recorded as an "opening" movement
     */
    public function create(array $data, string|int|float $openingQuantity = 0): Product
    {
        return DB::transaction(function () use ($data, $openingQuantity) {
            $product = Product::create($data);

            if (bccomp((string) $openingQuantity, '0', 3) > 0) {
                $this->stock->add($product, $openingQuantity, StockMovementType::Opening, $product->purchase_price, reason: 'Opening stock');
            }

            $this->activityLogger->log('product.created', $product, ['new' => $product->only(['sku', 'name', 'purchase_price', 'selling_price'])]);

            return $product->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product
    {
        $product->fill($data);
        $changes = $product->getDirty();
        $old = array_intersect_key($product->getOriginal(), $changes);
        $product->save();

        if ($changes !== []) {
            $this->activityLogger->log('product.updated', $product, ['old' => $old, 'new' => $changes]);
        }

        return $product;
    }

    /** Products with stock history are archived (soft-deleted), never hard-deleted. */
    public function archive(Product $product): void
    {
        $product->delete();
        $this->activityLogger->log('product.archived', $product);
    }

    public function restore(Product $product): void
    {
        $product->restore();
        $this->activityLogger->log('product.restored', $product);
    }
}
