<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Who paid, through which bank, and exactly when: the record-keeping details of a payment. */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_name')->nullable()->after('reference');
            $table->string('bank_name')->nullable()->after('payer_name');
            $table->dateTime('transacted_at')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_name', 'bank_name', 'transacted_at']);
        });
    }
};
