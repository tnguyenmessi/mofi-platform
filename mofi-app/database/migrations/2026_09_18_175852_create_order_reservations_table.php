<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->decimal('cash_amount', 24, 0)->default(0);
            $table->decimal('quantity', 24, 8)->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['portfolio_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reservations');
    }
};
