<?php

namespace Database\Factories;

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'user_id' => fn (array $attributes) => Portfolio::findOrFail($attributes['portfolio_id'])->user_id,
            'instrument_id' => null,
            'kind' => 'DEPOSIT',
            'trade_date' => '2026-09-15',
            'quantity' => null,
            'unit_price' => null,
            'gross_amount' => '30000000',
            'fee' => '0',
            'tax' => '0',
            'cash_delta' => '30000000',
            'request_key' => fake()->uuid(),
            'request_hash' => hash('sha256', 'test-deposit-30000000'),
        ];
    }
}
