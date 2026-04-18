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
        Schema::create('connectivity_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('endpoint')->default('/api/dashboard-data');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectivity_errors');
    }
};
