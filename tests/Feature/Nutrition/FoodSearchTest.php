<?php

use App\Models\User;
use OpenFoodFacts\Laravel\OpenFoodFacts as OpenFoodFactsClient;

use function Pest\Laravel\mock;

test('guests must authenticate before searching for foods', function () {
    $response = $this->getJson(route('foods.search', ['query' => 'apple']));

    $response->assertUnauthorized();
});

test('search returns normalized results from the nutrition service', function () {
    mock(OpenFoodFactsClient::class, function ($mock) {
        $mock->shouldReceive('find')
            ->once()
            ->with('macro bar')
            ->andReturn(collect([
                [
                    'code' => '009988776655',
                    'product_name' => 'Macro Bar Deluxe',
                    'serving_size' => '45 g',
                    'nutriments' => [
                        'energy-kcal_serving' => 210,
                        'proteins_serving' => 18,
                        'carbohydrates_serving' => 22,
                        'fat_serving' => 6,
                    ],
                ],
            ]));
    });

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson(route('foods.search', [
        'query' => 'macro bar',
        'limit' => 8,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.name', 'Macro Bar Deluxe')
        ->assertJsonPath('results.0.barcode', '009988776655')
        ->assertJsonPath('results.0.serving_size', 45.0)
        ->assertJsonPath('results.0.serving_unit', 'g')
        ->assertJsonPath('results.0.servings', 1.0)
        ->assertJsonPath('results.0.reference_quantity', 45.0)
        ->assertJsonPath('results.0.serving_quantity', 45.0)
        ->assertJsonPath('results.0.total_quantity', 45.0)
        ->assertJsonPath('results.0.default_quantity', 1.0)
        ->assertJsonPath('results.0.calories', 210)
        ->assertJsonPath('results.0.protein', 18)
        ->assertJsonPath('results.0.carb', 22)
        ->assertJsonPath('results.0.fat', 6)
        ->assertJsonPath('results.0.source', 'external');

    /** @var \Mockery\MockInterface $mockedClient */
    $mockedClient = app(OpenFoodFactsClient::class);
    $mockedClient->shouldHaveReceived('find')->once();
});

test('search requires at least two characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson(route('foods.search', ['query' => 'a']));

    $response->assertUnprocessable();
});
