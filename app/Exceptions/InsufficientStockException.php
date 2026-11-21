<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly Product $product, public readonly string $requested, public readonly string $available)
    {
        parent::__construct("Insufficient stock for {$product->name} ({$product->sku}): requested {$requested}, available {$available}.");
    }
}
