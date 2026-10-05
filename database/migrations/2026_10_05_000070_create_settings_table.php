<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic key/value settings (AI model, report language, VHP connection flags...).
     * property_id null = application-wide setting.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('group')->default('general'); // general, ai, import, branding
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
