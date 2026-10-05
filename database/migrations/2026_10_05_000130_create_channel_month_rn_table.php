<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections E/F — Channel Inside (Room Nights), one row per source per year,
     * with the 12 monthly values. YTD and % are computed. Prior years stack
     * below the current year, exactly like the spreadsheet.
     */
    public function up(): void
    {
        Schema::create('channel_month_rn', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('source_label');
            $table->unsignedInteger('jan')->default(0);
            $table->unsignedInteger('feb')->default(0);
            $table->unsignedInteger('mar')->default(0);
            $table->unsignedInteger('apr')->default(0);
            $table->unsignedInteger('may')->default(0);
            $table->unsignedInteger('jun')->default(0);
            $table->unsignedInteger('jul')->default(0);
            $table->unsignedInteger('aug')->default(0);
            $table->unsignedInteger('sep')->default(0);
            $table->unsignedInteger('oct')->default(0);
            $table->unsignedInteger('nov')->default(0);
            $table->unsignedInteger('dec')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_week_id', 'year', 'source_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_month_rn');
    }
};
