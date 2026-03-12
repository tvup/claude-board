<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('metric_name')->index();
            $table->string('metric_type')->default('sum');
            $table->double('value')->default(0);
            $table->string('unit')->nullable();
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
        Schema::dropIfExists('telemetry_metrics');
    }
};
