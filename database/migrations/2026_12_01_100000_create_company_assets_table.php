<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A company's logo and custom background. Stored in the database (base64) rather than on disk because the
     * hosting has no persistent disk: a file uploaded to one server instance would vanish on the next.
     */
    public function up(): void
    {
        Schema::create('company_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // logo | background
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->mediumText('data'); // base64
            $table->timestamps();

            $table->unique(['company_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_assets');
    }
};
