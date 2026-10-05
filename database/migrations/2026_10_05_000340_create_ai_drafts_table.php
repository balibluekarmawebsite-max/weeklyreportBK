<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI generations (Phase 7). An audit trail of every Groq draft / rewrite so we
 * can show what the model produced, with which model, for whom — and never
 * silently pass AI text off as human-written. The live "AI draft vs approved"
 * state for Section A stays on overview_blocks.ai_draft; this table is history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('section');            // overview, activity, action_plan
            $table->string('field_key')->nullable(); // e.g. overview block key, or 'notes' / 'remark'
            $table->string('mode');               // draft, rewrite, shorten, translate_en, translate_id
            $table->string('model');              // the Groq model that produced it
            $table->longText('output');
            $table->string('status')->default('draft'); // draft, approved
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['report_week_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_drafts');
    }
};
