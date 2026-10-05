<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section D — Rate Code / Promotion production. ARR and % are computed.
     */
    public function up(): void
    {
        Schema::create('rate_code_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('rn_sold')->nullable();
            $table->decimal('gross_revenue', 16, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_code_productions');
    }
};
