<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\Instrument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AlertRule> */
class AlertRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instrument_id' => Instrument::factory(),
            'operator' => 'GTE',
            'threshold' => '125000',
            'enabled' => true,
            'last_condition' => null,
        ];
    }
}
