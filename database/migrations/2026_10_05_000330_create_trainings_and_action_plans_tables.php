<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Section I — Training
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('date_label')->nullable();
            $table->string('topic');
            $table->string('duration')->nullable();
            $table->string('trainer')->nullable();
            $table->text('participants')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Section J — Next Week Action Plan (grouped by category)
        Schema::create('action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('plan');
            $table->string('start_label')->nullable();
            $table->string('deadline_label')->nullable();
            $table->text('remark')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_plans');
        Schema::dropIfExists('trainings');
    }
};
