<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sections G (Sales Activity), G2 (E-commerce) and Marketing daily logs.
     * One table, distinguished by `department`. Dates are kept as free-form
     * labels because the source sheet uses mixed formats.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('department');        // sales, ecommerce, marketing
            $table->string('date_label')->nullable();
            $table->string('title')->nullable();  // subject / task / company
            $table->text('notes')->nullable();    // remarks / PIC / market / update
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['report_week_id', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
