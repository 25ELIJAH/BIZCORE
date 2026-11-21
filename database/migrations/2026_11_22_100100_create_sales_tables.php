<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free, per-company, per-year numbering for invoices, purchase orders, etc.
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['company_id', 'type', 'year']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete(); // null = walk-in
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sale_date');
            $table->date('due_date');
            $table->string('status', 20)->default('completed');          // completed | cancelled
            $table->string('payment_status', 20)->default('unpaid');     // unpaid | partial | paid
            $table->decimal('subtotal', 15, 2);
            $table->string('discount_type', 10)->default('fixed');       // fixed | percent
            $table->decimal('discount_value', 15, 2)->default(0);         // what the user entered
            $table->decimal('discount_amount', 15, 2)->default(0);       // computed amount taken off
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);            // sum of non-voided payments
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sale_date']);
            $table->index(['company_id', 'status', 'payment_status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'due_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Snapshots, so invoices never change when a product is edited later.
            $table->string('product_name');
            $table->string('sku', 64);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->index(['company_id', 'product_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40);
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->date('paid_at');
            $table->string('reference')->nullable();
            // Client-generated token: resubmitting the same form cannot record the payment twice.
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'sale_id']);
            $table->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_sequences');
    }
};
