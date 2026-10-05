<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();              // e.g. BKDS, BKDU, BKV
            $table->string('name');                         // Blue Karma Dijiwa Seminyak
            $table->unsignedSmallInteger('rooms_count')->default(0); // total rooms, for occupancy %
            $table->string('currency', 3)->default('IDR');
            $table->string('timezone')->default('Asia/Makassar');
            // Branding for the dashboard + exports.
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->default('#0F3D3E');  // deep teal
            $table->string('accent_color', 9)->default('#C9A24B');   // warm gold accent
            $table->string('export_footer')->default('Confidential');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
