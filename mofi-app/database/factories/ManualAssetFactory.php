<?php

namespace Database\Factories;

use App\Models\ManualAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ManualAsset> */
class ManualAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Test asset',
            'category' => 'other',
            'current_value' => '5000000',
            'valued_on' => '2026-09-15',
        ];
    }
}
