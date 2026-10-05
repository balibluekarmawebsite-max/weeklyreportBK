<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner Overview — repeater-guest 12-month performance and channel mix.
     */
    public function up(): void
    {
        Schema::create('owner_repeater_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // e.g. "September 2025"
            $table->unsignedInteger('room_nights')->nullable();
            $table->decimal('revenue', 16, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('owner_channel_mix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // OTA, Direct Booking, Offline Travel Agents
            $table->unsignedInteger('rn_sold')->nullable();
            $table->decimal('gross_revenue', 16, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_channel_mix');
        Schema::dropIfExists('owner_repeater_months');
    }
};
