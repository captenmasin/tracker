<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MacroGoal>
 */
class MacroGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'daily_calorie_goal' => fake()->numberBetween(1800, 2600),
            'protein_percentage' => 30,
            'carb_percentage' => 40,
            'fat_percentage' => 30,
        ];
    }
}
