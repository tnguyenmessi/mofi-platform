<?php

namespace Database\Factories;

use App\Models\SimulationScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SimulationScenario> */
class SimulationScenarioFactory extends Factory
{
    protected $model = SimulationScenario::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => 'Giảm 10%', 'shock_percent' => 10, 'before_value' => '10000000', 'after_value' => '9500000', 'change_value' => '-500000'];
    }
}
