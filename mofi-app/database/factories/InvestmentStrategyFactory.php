<?php

namespace Database\Factories;

use App\Models\InvestmentStrategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvestmentStrategy> */
class InvestmentStrategyFactory extends Factory
{
    protected $model = InvestmentStrategy::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => 'Chiến lược cân bằng', 'risk_profile' => 'balanced', 'allocation' => ['cash' => 30, 'stocks' => 50, 'other' => 20], 'notes' => 'Minh họa'];
    }
}
