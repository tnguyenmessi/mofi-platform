<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 24, 8);
            $table->decimal('unit_price', 24, 8);
            $table->decimal('gross_amount', 24, 0);
            $table->decimal('fee', 24, 0)->default(0);
            $table->decimal('tax', 24, 0)->default(0);
            $table->string('source', 40)->default('demo_replay');
            $table->timestamp('executed_at');
            $table->timestamps();
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
