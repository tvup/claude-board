<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->string('project_name')->nullable()->after('terminal_type');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->dropColumn('project_name');
        });
    }
};
