<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section C — Weekly Production by Market Segment (last 7 days).
     * ARR and % are computed from rn_sold + gross_revenue, never stored as
     * error cells.
     */
    public function up(): void
    {
        Schema::create('segment_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('segment_group')->nullable(); // e.g. OTA, Direct, Offline
            $table->unsignedInteger('rn_sold')->nullable();
            $table->decimal('gross_revenue', 16, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_productions');
    }
};
