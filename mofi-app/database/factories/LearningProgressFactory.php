<?php

namespace Database\Factories;

use App\Models\LearningProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningProgress> */
class LearningProgressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_slug' => 'portfolio-basics',
            'completed_at' => now(),
        ];
    }
}
