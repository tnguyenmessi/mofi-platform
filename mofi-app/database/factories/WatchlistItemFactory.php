<?php

namespace Database\Factories;

use App\Models\Instrument;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WatchlistItem> */
class WatchlistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instrument_id' => Instrument::factory(),
        ];
    }
}
