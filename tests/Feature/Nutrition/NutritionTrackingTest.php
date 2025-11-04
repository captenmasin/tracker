<?php

use App\Models\CalorieBurnEntry;
use App\Models\Food;
use App\Models\FoodEntry;
use App\Models\MacroGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use OpenFoodFacts\Laravel\OpenFoodFacts as OpenFoodFactsClient;

use function Pest\Laravel\mock;

test('users can save foods to their library', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('foods.store'), [
        'name' => 'Sample Protein Shake',
        'barcode' => '1234567890123',
        'serving_size' => 1,
        'serving_unit' => 'serving',
        'calories_per_serving' => 210,
        'protein_grams' => 28,
        'carb_grams' => 8,
        'fat_grams' => 6,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('foods', [
        'user_id' => $user->id,
        'name' => 'Sample Protein Shake',
        'barcode' => '1234567890123',
        'calories_per_serving' => 210,
        'protein_grams' => 28,
        'carb_grams' => 8,
        'fat_grams' => 6,
    ]);
});

test('logging from the library scales nutrients by quantity', function () {
    $user = User::factory()->create();
    $food = Food::factory()
        ->for($user)
        ->state([
            'calories_per_serving' => 320,
            'protein_grams' => 25,
            'carb_grams' => 30,
            'fat_grams' => 12,
        ])
        ->create();

    $this->actingAs($user);

    $response = $this->post(route('food-entries.store'), [
        'food_id' => $food->id,
        'quantity' => 2,
        'consumed_on' => '2025-10-30',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('food_entries', [
        'user_id' => $user->id,
        'food_id' => $food->id,
        'quantity' => 2,
        'calories' => 640.0,
        'protein_grams' => 50.0,
        'carb_grams' => 60.0,
        'fat_grams' => 24.0,
    ]);
});

test('users can log manual entries with precise macros', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('food-entries.store'), [
        'name' => 'Homemade smoothie',
        'calories' => 180,
        'protein_grams' => 12,
        'carb_grams' => 22,
        'fat_grams' => 4,
        'quantity' => 1.5,
        'serving_unit' => 'glass',
        'consumed_on' => '2025-10-30',
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('food_entries', [
        'user_id' => $user->id,
        'food_id' => null,
        'name' => 'Homemade smoothie',
        'quantity' => 1.5,
        'calories' => 180.0,
        'protein_grams' => 12.0,
        'carb_grams' => 22.0,
        'fat_grams' => 4.0,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    $this->assertDatabaseHas('foods', [
        'user_id' => $user->id,
        'name' => 'Homemade smoothie',
    ]);
});

test('calorie burns are tracked per day', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('calorie-burn-entries.store'), [
        'calories' => 350,
        'recorded_on' => '2025-10-30',
        'description' => 'Evening run',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('calorie_burn_entries', [
        'user_id' => $user->id,
        'calories' => 350,
        'description' => 'Evening run',
        'recorded_on' => '2025-10-30 00:00:00',
    ]);
});

test('barcode lookup falls back to an external nutrition service when needed', function () {
    mock(OpenFoodFactsClient::class, function ($mock) {
        $mock->shouldReceive('barcode')
            ->once()
            ->with('0099887766554')
            ->andReturn([
                'product_name' => 'API Macro Bar',
                'serving_size' => '45 g',
                'serving_quantity' => '45',
                'nutriments' => [
                    'energy-kcal_serving' => 210,
                    'proteins_serving' => 18,
                    'carbohydrates_serving' => 22,
                    'fat_serving' => 6,
                ],
            ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->getJson(route('foods.barcode', '0099887766554'));

    $response->assertOk()
        ->assertJsonPath('source', 'external')
        ->assertJsonPath('food.name', 'API Macro Bar')
        ->assertJsonPath('food.barcode', '0099887766554')
        ->assertJsonPath('food.serving_size', 45)
        ->assertJsonPath('food.serving_unit', 'g')
        ->assertJsonPath('food.servings', 1)
        ->assertJsonPath('food.default_quantity', 1)
        ->assertJsonPath('food.calories_total', 210)
        ->assertJsonPath('food.protein_total', 18)
        ->assertJsonPath('food.carb_total', 22)
        ->assertJsonPath('food.fat_total', 6)
        ->assertJsonPath('food.reference_quantity', 45);

    /** @var \Mockery\MockInterface $client */
    $client = app(OpenFoodFactsClient::class);
    $client->shouldHaveReceived('barcode')->once();
});

test('dashboard summary includes macros, burns, and remaining goals', function () {
    $date = Carbon::parse('2025-10-30');

    $user = User::factory()->create();
    $this->actingAs($user);

    MacroGoal::factory()->for($user)->create([
        'daily_calorie_goal' => 2000,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    FoodEntry::factory()->for($user)->create([
        'food_id' => null,
        'name' => 'Protein bowl',
        'calories' => 500,
        'protein_grams' => 40,
        'carb_grams' => 35,
        'fat_grams' => 12,
        'consumed_on' => $date,
        'quantity' => 1,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    FoodEntry::factory()->for($user)->create([
        'food_id' => null,
        'name' => 'Recovery shake',
        'calories' => 220,
        'protein_grams' => 18,
        'carb_grams' => 20,
        'fat_grams' => 5,
        'consumed_on' => $date,
        'quantity' => 1,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    CalorieBurnEntry::factory()->for($user)->create([
        'calories' => 180,
        'recorded_on' => $date,
        'description' => 'Rowing session',
    ]);

    $response = $this->get(route('dashboard', ['date' => $date->toDateString()]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('summary.calories.consumed', 720)
        ->where('summary.calories.burned', 180)
        ->where('summary.calories.net', 540)
        ->where('summary.calories.goal', 2000)
        ->where('summary.calories.remaining', 1460)
        ->where('summary.macros.protein.consumed', 58)
        ->where('summary.macros.protein.goal', fn ($value) => abs($value - 163.5) < 0.05)
        ->where('summary.macros.protein.remaining', fn ($value) => abs($value - 105.5) < 0.05)
        ->where('summary.entries.foods', fn ($entries) => collect($entries)->pluck('name')->contains('Protein bowl')
            && collect($entries)->pluck('name')->contains('Recovery shake')
            && count($entries) === 2)
        ->where('summary.entries.burns.0.calories', 180)
        ->where('summary.weekly.totals.net', 540)
        ->where('summary.weekly.totals.protein', 58)
        ->where('summary.weekly.totals.carb', 55)
        ->where('summary.weekly.totals.fat', 17)
        ->where('summary.weekly.days', function ($days) use ($date) {
            $day = collect($days)->firstWhere('date', $date->toDateString());

            if ($day === null) {
                return false;
            }

            return abs($day['macros']['protein'] - 58) < 0.01
                && abs($day['macros']['carb'] - 55) < 0.01
                && abs($day['macros']['fat'] - 17) < 0.01;
        })
        ->where('summary.macros.carb.consumed', 55)
        ->where('summary.macros.fat.consumed', 17));
});

test('weekly overview page provides goal comparisons', function () {
    $date = Carbon::parse('2025-10-30');

    $user = User::factory()->create();
    $this->actingAs($user);

    MacroGoal::factory()->for($user)->create([
        'daily_calorie_goal' => 2000,
        'protein_percentage' => 30,
        'carb_percentage' => 40,
        'fat_percentage' => 30,
    ]);

    FoodEntry::factory()->for($user)->create([
        'food_id' => null,
        'name' => 'Protein bowl',
        'calories' => 500,
        'protein_grams' => 40,
        'carb_grams' => 35,
        'fat_grams' => 12,
        'consumed_on' => $date,
        'quantity' => 1,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    FoodEntry::factory()->for($user)->create([
        'food_id' => null,
        'name' => 'Recovery shake',
        'calories' => 220,
        'protein_grams' => 18,
        'carb_grams' => 20,
        'fat_grams' => 5,
        'consumed_on' => $date,
        'quantity' => 1,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    FoodEntry::factory()->for($user)->create([
        'food_id' => null,
        'name' => 'Carb load',
        'calories' => 600,
        'protein_grams' => 20,
        'carb_grams' => 90,
        'fat_grams' => 10,
        'consumed_on' => $date->copy()->subDay(),
        'quantity' => 1,
        'source' => FoodEntry::SOURCE_MANUAL,
    ]);

    CalorieBurnEntry::factory()->for($user)->create([
        'calories' => 320,
        'recorded_on' => $date->copy()->subDays(2),
        'description' => 'Intervals session',
    ]);

    $response = $this->get(route('dashboard.weekly', ['date' => $date->toDateString()]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard/WeeklyOverview')
        ->where('week.start', '2025-10-24')
        ->where('week.end', '2025-10-30')
        ->where('week.days', function ($days) use ($date) {
            if (count($days) !== 7) {
                return false;
            }

            $selectedDay = collect($days)->firstWhere('date', $date->toDateString());
            $previousDay = collect($days)->firstWhere('date', $date->copy()->subDay()->toDateString());

            if ($selectedDay === null || $previousDay === null) {
                return false;
            }

            return abs($selectedDay['calories'] - 720) < 0.01
                && abs($previousDay['calories'] - 600) < 0.01;
        })
        ->where('week.totals.protein', 78.0)
        ->where('week.totals.carb', 145.0)
        ->where('week.totals.fat', 27.0)
        ->where('macroGoal.targets.protein', fn ($value) => abs($value - 150) < 0.1)
        ->where('date.current', $date->toDateString())
        ->where('date.previous', $date->copy()->subWeek()->toDateString())
        ->where('date.next', $date->copy()->addWeek()->toDateString())
        ->where('foods', function ($foods) {
            if (count($foods) !== 3) {
                return false;
            }

            $carbLoad = collect($foods)->firstWhere('name', 'Carb load');

            return $carbLoad !== null
                && abs($carbLoad['carb'] - 90) < 0.01
                && collect($foods)->every(fn ($food) => isset($food['calories'], $food['weekday']));
        })
        ->where('burns.0.calories', 320)
        ->where('burns.0.description', 'Intervals session'));
});
