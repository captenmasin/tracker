<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalorieBurnEntry>
 */
class CalorieBurnEntryFactory extends Factory
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
            'recorded_on' => fake()->date(),
            'calories' => fake()->numberBetween(100, 800),
            'description' => fake()->sentence(3),
        ];
    }
}
