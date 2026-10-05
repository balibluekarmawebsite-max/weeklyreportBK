<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section A — Sales & Marketing Overview. Each numbered sub-section
     * (Financial, Market overview, Pace, Countries, Booking window,
     * Booking.com ranking, Learning & growth) is one text block.
     */
    public function up(): void
    {
        Schema::create('overview_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('key');        // financial, market, pace, countries, booking_window, booking_ranking, learning
            $table->string('heading');
            $table->longText('body')->nullable();
            $table->boolean('ai_draft')->default(false); // marked until a person approves
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_week_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overview_blocks');
    }
};
