<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id')->unique()->index();
            $table->string('user_email')->nullable();
            $table->string('user_id')->nullable();
            $table->string('account_uuid')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('app_version')->nullable();
            $table->string('terminal_type')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_sessions');
    }
};
