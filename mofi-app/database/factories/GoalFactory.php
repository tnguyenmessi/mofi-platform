<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Goal> */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Test goal',
            'category' => 'emergency',
            'target_amount' => '10000000',
            'saved_amount' => '2000000',
            'target_date' => '2027-09-15',
        ];
    }
}
