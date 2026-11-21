<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);               // income | expense
            $table->string('name', 100);
            $table->boolean('is_system')->default(false);   // created by the app for derived entries; cannot be edited
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'type', 'name']);
        });

        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);               // income | expense (always equals the category's type)
            $table->decimal('amount', 15, 2);
            $table->date('entry_date');
            $table->string('description', 255);
            $table->string('reference', 100)->nullable();
            // Derived entries point at the payment that produced them. The unique key makes double counting impossible.
            $table->string('source_type', 30)->nullable();   // payment | supplier_payment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source_type', 'source_id']);
            $table->index(['company_id', 'type', 'entry_date']);
            $table->index(['company_id', 'finance_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('finance_categories');
    }
};
