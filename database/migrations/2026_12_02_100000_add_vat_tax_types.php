<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** KRA VAT: a tax type per product (kept on every sale line) and the VAT split of each sale. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tax_type', 12)->default('standard')->after('selling_price'); // standard | zero_rated | exempt
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('tax_type', 12)->default('standard')->after('line_total');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Net amounts (after discount) by VAT category; they add up to subtotal minus discount.
            $table->decimal('vatable_amount', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('zero_rated_amount', 15, 2)->default(0)->after('vatable_amount');
            $table->decimal('exempt_amount', 15, 2)->default(0)->after('zero_rated_amount');
        });

        // Sales made before this change: if VAT was charged the net is vatable, otherwise no VAT was charged on it.
        DB::table('sales')->where('tax_rate', '>', 0)->update(['vatable_amount' => DB::raw('subtotal - discount_amount')]);
        DB::table('sales')->where('tax_rate', '<=', 0)->update(['exempt_amount' => DB::raw('subtotal - discount_amount')]);
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn(['vatable_amount', 'zero_rated_amount', 'exempt_amount']));
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropColumn('tax_type'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('tax_type'));
    }
};
