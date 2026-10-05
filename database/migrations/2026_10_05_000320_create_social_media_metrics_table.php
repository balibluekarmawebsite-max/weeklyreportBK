<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section H — Social Media Insight. One row per metric; growth and growth %
     * are computed from last week vs this week.
     */
    public function up(): void
    {
        Schema::create('social_media_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_week_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('Instagram');
            $table->string('metric_key');   // website_visit, profile_visit, account_reached, impression, followers
            $table->bigInteger('last_week')->nullable();
            $table->bigInteger('this_week')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_metrics');
    }
};
