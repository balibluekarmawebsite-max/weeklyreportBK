<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yearly budget, one row per month. Uploaded once a year in Settings.
     * Used in Section B (Actual vs Budget vs Last Year).
     */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->unsignedInteger('rn_sold')->default(0);        // room nights budgeted
            $table->decimal('occupancy', 6, 3)->default(0);        // percent, e.g. 72.500
            $table->decimal('arr', 14, 2)->default(0);             // average room rate (IDR)
            $table->decimal('revenue', 16, 2)->default(0);         // room revenue (IDR)
            $table->timestamps();

            $table->unique(['property_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
