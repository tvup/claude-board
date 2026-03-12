<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry_metrics', function (Blueprint $table) {
            $table->index('recorded_at');
            $table->index(['session_id', 'recorded_at']);
        });

        Schema::table('telemetry_events', function (Blueprint $table) {
            $table->index('recorded_at');
            $table->index(['session_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_metrics', function (Blueprint $table) {
            $table->dropIndex(['recorded_at']);
            $table->dropIndex(['session_id', 'recorded_at']);
        });

        Schema::table('telemetry_events', function (Blueprint $table) {
            $table->dropIndex(['recorded_at']);
            $table->dropIndex(['session_id', 'recorded_at']);
        });
    }
};
