<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests must authenticate before searching for foods', function () {
    $response = $this->getJson(route('foods.search', ['query' => 'apple']));

    $response->assertUnauthorized();
});

test('search returns normalized results from the nutrition service', function () {
    config()->set('services.nutrition.search_endpoint', 'https://nutrition.test/search');
    config()->set('services.nutrition.language', 'en');
    config()->set('services.nutrition.country', 'us');

    Http::fake([
        'https://nutrition.test/search*' => Http::response([
            'products' => [
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
            ],
        ]),
    ]);

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
        ->assertJsonPath('results.0.serving_unit', 'g')
        ->assertJsonPath('results.0.calories', 210)
        ->assertJsonPath('results.0.protein', 18)
        ->assertJsonPath('results.0.carb', 22)
        ->assertJsonPath('results.0.fat', 6)
        ->assertJsonPath('results.0.source', 'external');

    Http::assertSent(function ($request) {
        $url = $request->url();
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
        parse_str($queryString, $params);

        return str_starts_with($url, 'https://nutrition.test/search')
            && $request->method() === 'GET'
            && ($params['search_terms'] ?? null) === 'macro bar'
            && ($params['page_size'] ?? null) === '8'
            && ($params['fields'] ?? null) === 'code,product_name,product_name_en,serving_size,serving_quantity,product_quantity,product_quantity_unit,nutriments'
            && ($params['lc'] ?? null) === 'en'
            && ($params['cc'] ?? null) === 'us'
            && $request->hasHeader('User-Agent');
    });
});

test('search requires at least two characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson(route('foods.search', ['query' => 'a']));

    $response->assertUnprocessable();
});
