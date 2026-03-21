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
        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->string('hostname')->nullable()->after('billing_model');
        });
    }

    public function down(): void
    {
        Schema::table('telemetry_sessions', function (Blueprint $table) {
            $table->dropColumn('hostname');
        });
    }
};
