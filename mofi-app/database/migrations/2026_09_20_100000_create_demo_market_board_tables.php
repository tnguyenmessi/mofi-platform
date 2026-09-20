<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_market_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->string('status', 16)->default('OPEN');
            $table->integer('current_tick')->default(0);
            $table->timestampTz('simulated_at')->nullable();
            $table->timestampTz('last_advanced_at')->nullable();
            $table->unsignedInteger('real_interval_seconds')->default(5);
            $table->unsignedInteger('simulated_interval_minutes')->default(5);
            $table->unsignedInteger('revision')->default(0);
            $table->timestampsTz();
            $table->unique(['instrument_id', 'session_date']);
            $table->index(['instrument_id', 'status']);
        });

        Schema::create('demo_market_ticks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('demo_market_sessions')->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->integer('tick');
            $table->timestampTz('simulated_at');
            $table->decimal('reference_price', 24, 8);
            $table->decimal('ceiling_price', 24, 8);
            $table->decimal('floor_price', 24, 8);
            $table->decimal('last_price', 24, 8);
            $table->decimal('last_quantity', 24, 8)->default(0);
            $table->decimal('total_volume', 24, 8)->default(0);
            $table->decimal('bid1_price', 24, 8);
            $table->decimal('bid1_quantity', 24, 8)->default(0);
            $table->decimal('bid2_price', 24, 8);
            $table->decimal('bid2_quantity', 24, 8)->default(0);
            $table->decimal('bid3_price', 24, 8);
            $table->decimal('bid3_quantity', 24, 8)->default(0);
            $table->decimal('ask1_price', 24, 8);
            $table->decimal('ask1_quantity', 24, 8)->default(0);
            $table->decimal('ask2_price', 24, 8);
            $table->decimal('ask2_quantity', 24, 8)->default(0);
            $table->decimal('ask3_price', 24, 8);
            $table->decimal('ask3_quantity', 24, 8)->default(0);
            $table->string('source', 32)->default('demo_market');
            $table->boolean('is_demo')->default(true);
            $table->timestampsTz();
            $table->unique(['session_id', 'tick']);
            $table->index(['instrument_id', 'simulated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_market_ticks');
        Schema::dropIfExists('demo_market_sessions');
    }
};
