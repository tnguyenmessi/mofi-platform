<?php

namespace Database\Factories;

use App\Models\Instrument;
use App\Models\MarketPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketPrice> */
class MarketPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instrument_id' => Instrument::factory(),
            'price_date' => '2026-09-15',
            'close' => '125000',
            'reference_close' => '124000',
            'source' => 'demo',
            'is_demo' => true,
        ];
    }
}
