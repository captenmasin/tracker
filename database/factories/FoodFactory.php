<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Food>
 */
class FoodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $protein = fake()->numberBetween(5, 40);
        $carb = fake()->numberBetween(5, 60);
        $fat = fake()->numberBetween(1, 25);
        $calories = ($protein + $carb) * 4 + $fat * 9;

        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'barcode' => fake()->unique()->ean13(),
            'serving_size' => 1,
            'serving_unit' => 'serving',
            'calories_per_serving' => $calories,
            'protein_grams' => $protein,
            'carb_grams' => $carb,
            'fat_grams' => $fat,
        ];
    }
}
