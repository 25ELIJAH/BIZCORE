<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            // Weighted-average unit cost, maintained by StockService on every stock-in with a cost.
            $table->decimal('average_cost', 15, 4)->default(0);
            // Current on-hand quantity. Only StockService may change it (every change writes a stock_movement).
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // archived products keep their history

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'product_category_id']);
        });

        // Append-only ledger of every quantity change.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 15, 3);          // signed change: + in, - out
            $table->decimal('previous_quantity', 15, 3);
            $table->decimal('new_quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->nullableMorphs('reference');         // originating sale / goods receipt, when any
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'product_id', 'created_at']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
