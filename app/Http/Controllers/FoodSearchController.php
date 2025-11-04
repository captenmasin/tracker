<?php

namespace App\Http\Controllers;

use App\Http\Requests\Nutrition\SearchFoodRequest;
use App\Services\Nutrition\ProductSearch;
use Illuminate\Http\JsonResponse;

class FoodSearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SearchFoodRequest $request, ProductSearch $search): JsonResponse
    {
        $validated = $request->validated();

        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 10;

        $results = $search->search($validated['query'], $limit);

        return response()->json([
            'results' => array_map(static fn (array $result) => [
                ...$result,
                'source' => 'external',
            ], $results),
        ]);
    }
}
