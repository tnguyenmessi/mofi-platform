<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->integer('placed_tick')->nullable()->after('request_hash');
            $table->timestampTz('placed_at_simulated')->nullable()->after('placed_tick');
            $table->decimal('reserved_unit_price', 24, 8)->nullable()->after('placed_at_simulated');
        });

        Schema::table('executions', function (Blueprint $table): void {
            $table->uuid('execution_key')->nullable()->unique()->after('transaction_id');
            $table->timestampTz('simulated_at')->nullable()->after('executed_at');
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table): void {
            $table->dropUnique(['execution_key']);
            $table->dropColumn(['execution_key', 'simulated_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['placed_tick', 'placed_at_simulated', 'reserved_unit_price']);
        });
    }
};
