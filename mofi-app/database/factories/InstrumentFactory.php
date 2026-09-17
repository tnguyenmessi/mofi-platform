<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Instrument> */
class InstrumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'symbol' => strtoupper(fake()->unique()->bothify('T????###')),
            'name' => 'Test equity',
            'market' => 'VN',
            'asset_class' => 'stock',
            'currency' => 'VND',
            'price_unit' => 'share',
            'tradable' => true,
        ];
    }
}
