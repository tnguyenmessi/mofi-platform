<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
            $table->index(['order_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'executed_at']);
            $table->unique('order_id');
        });
    }
};
