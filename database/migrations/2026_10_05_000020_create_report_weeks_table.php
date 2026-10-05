<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');                 // e.g. 2026-09-25 (Fri)
            $table->date('end_date');                   // e.g. 2026-10-01 (Thu)
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('week_number');
            $table->string('label')->nullable();        // "25 Sep – 1 Oct 2026"
            $table->string('status')->default('draft'); // App\Enums\ReportStatus
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_weeks');
    }
};
