<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('event_name')->index();
            $table->string('severity')->nullable();
            $table->text('body')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamp('recorded_at')->nullable();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('telemetry_sessions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
    }
};
