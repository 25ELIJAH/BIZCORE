<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** BizCore is Kenyan-shilling only: convert existing companies and change the column default. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->update(['currency' => 'KES']);

        Schema::table('companies', function ($table) {
            $table->char('currency', 3)->default('KES')->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function ($table) {
            $table->char('currency', 3)->default('USD')->change();
        });
    }
};
