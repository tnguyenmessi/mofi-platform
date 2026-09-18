<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            $table->string('side', 4);
            $table->string('order_type', 6);
            $table->decimal('quantity', 24, 8);
            $table->decimal('limit_price', 24, 8)->nullable();
            $table->decimal('filled_quantity', 24, 8)->default(0);
            $table->string('status', 20)->default('OPEN');
            $table->string('request_key', 80);
            $table->string('request_hash', 64);
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['portfolio_id', 'request_key']);
            $table->index(['portfolio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
