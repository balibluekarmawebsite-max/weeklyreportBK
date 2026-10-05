<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Department is only relevant for Contributor-role users.
            $table->string('department')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('department');
            $table->string('job_title')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'is_active', 'job_title']);
        });
    }
};
