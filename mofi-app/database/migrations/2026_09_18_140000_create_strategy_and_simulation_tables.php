<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_strategies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('risk_profile', 24);
            $table->json('allocation');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('simulation_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('shock_percent');
            $table->decimal('before_value', 24, 0);
            $table->decimal('after_value', 24, 0);
            $table->decimal('change_value', 24, 0);
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_scenarios');
        Schema::dropIfExists('investment_strategies');
    }
};
