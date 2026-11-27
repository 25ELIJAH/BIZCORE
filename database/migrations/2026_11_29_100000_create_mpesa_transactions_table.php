<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mpesa_transactions')) {
            return;
        }

        // One row per STK push. The row is the audit trail: what was asked, what Safaricom answered,
        // and which payment it turned into. Rows are never deleted.
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();            // goes into the callback URL, so only Safaricom (who we told) can call it
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // who pressed the button
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');   // pending | success | failed | needs_review
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('mpesa_receipt', 30)->nullable()->index();
            $table->integer('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
