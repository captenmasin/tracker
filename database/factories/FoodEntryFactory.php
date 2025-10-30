<?php

namespace Database\Factories;

use App\Models\FoodEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FoodEntry>
 */
class FoodEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $protein = fake()->numberBetween(10, 60);
        $carb = fake()->numberBetween(10, 80);
        $fat = fake()->numberBetween(5, 30);
        $calories = ($protein + $carb) * 4 + $fat * 9;

        return [
            'user_id' => User::factory(),
            'food_id' => null,
            'name' => fake()->words(3, true),
            'barcode' => null,
            'consumed_on' => fake()->date(),
            'quantity' => 1,
            'serving_unit' => 'serving',
            'calories' => $calories,
            'protein_grams' => $protein,
            'carb_grams' => $carb,
            'fat_grams' => $fat,
            'source' => FoodEntry::SOURCE_MANUAL,
        ];
    }
}
