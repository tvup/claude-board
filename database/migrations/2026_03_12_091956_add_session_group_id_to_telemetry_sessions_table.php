<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('telemetry_sessions', 'session_group_id')) {
            return;
        }

        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->string('session_group_id')->nullable()->after('session_id');
            $table->index('session_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->dropIndex(['session_group_id']);
            $table->dropColumn('session_group_id');
        });
    }
};
