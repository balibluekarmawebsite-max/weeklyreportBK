<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How an import arrived: a person uploading a file ('manual') or the VHP robot
 * posting to the secure endpoint ('vhp'). See docs/PLAN.md sections 4 & 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('report_week_id'); // manual, vhp
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
