<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section B — Year to Date Actual & On-Hand Forecast.
     * One row per month, snapshotted on the report week so past weeks
     * re-export exactly as they were. Occupancy is stored as a fraction (0–1).
     */
    public function up(): void
    {
        Schema::create('monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedInteger('rn_sold')->nullable();
            $table->decimal('occ_actual', 7, 4)->nullable();
            $table->decimal('occ_budget', 7, 4)->nullable();
            $table->decimal('occ_ly', 7, 4)->nullable();
            $table->decimal('arr_actual', 14, 2)->nullable();
            $table->decimal('arr_budget', 14, 2)->nullable();
            $table->decimal('arr_ly', 14, 2)->nullable();
            $table->decimal('rev_actual', 16, 2)->nullable();
            $table->decimal('rev_budget', 16, 2)->nullable();
            $table->decimal('rev_ly', 16, 2)->nullable();
            $table->timestamps();

            $table->unique(['report_week_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_stats');
    }
};
