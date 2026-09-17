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
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->char('currency', 3)->default('VND');
            $table->timestampsTz();
            $table->unique('user_id');
            $table->unique(['id', 'user_id']);
        });
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32);
            $table->string('name', 120);
            $table->string('market', 32);
            $table->string('asset_class', 24);
            $table->string('sector', 80)->nullable();
            $table->char('currency', 3)->default('VND');
            $table->string('price_unit', 32)->default('share');
            $table->boolean('tradable')->default(false);
            $table->timestampsTz();
            $table->unique(['market', 'symbol']);
        });
        Schema::create('market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('price_date');
            $table->decimal('close', 24, 8);
            $table->decimal('reference_close', 24, 8)->nullable();
            $table->string('source', 32)->default('demo');
            $table->boolean('is_demo')->default(true);
            $table->timestampsTz();
            $table->unique(['instrument_id', 'price_date']);
            $table->index(['instrument_id', 'price_date']);
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('portfolio_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->date('trade_date');
            $table->decimal('quantity', 24, 8)->nullable();
            $table->decimal('unit_price', 24, 8)->nullable();
            $table->decimal('gross_amount', 24, 0);
            $table->decimal('fee', 24, 0)->default(0);
            $table->decimal('tax', 24, 0)->default(0);
            $table->decimal('cash_delta', 24, 0);
            $table->uuid('request_key');
            $table->char('request_hash', 64);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['portfolio_id', 'request_key']);
            $table->index(['portfolio_id', 'trade_date', 'id']);
            $table->foreign(['portfolio_id', 'user_id'])->references(['id', 'user_id'])->on('portfolios')->restrictOnDelete();
        });
        Schema::create('manual_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 24);
            $table->decimal('current_value', 24, 0);
            $table->date('valued_on');
            $table->timestampsTz();
        });
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 24);
            $table->decimal('target_amount', 24, 0);
            $table->decimal('saved_amount', 24, 0)->default(0);
            $table->date('target_date')->nullable();
            $table->timestampsTz();
        });
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id', 'instrument_id']);
        });
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('operator', 3);
            $table->decimal('threshold', 24, 8);
            $table->boolean('enabled')->default(true);
            $table->boolean('last_condition')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 240);
            $table->text('body');
            $table->foreignId('source_rule_id')->nullable();
            $table->decimal('observed_price', 24, 8)->nullable();
            $table->date('source_date')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'read_at', 'created_at']);
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 240);
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'due_date']);
        });
        Schema::create('learning_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_slug', 80);
            $table->timestampTz('completed_at');
            $table->timestampsTz();
            $table->unique(['user_id', 'lesson_slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['learning_progress', 'tasks', 'notifications', 'alert_rules', 'watchlist_items', 'goals', 'manual_assets', 'transactions', 'market_prices', 'instruments', 'portfolios'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
