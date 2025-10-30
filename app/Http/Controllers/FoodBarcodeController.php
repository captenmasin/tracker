<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Services\Nutrition\BarcodeLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodBarcodeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BarcodeLookup $lookup, string $barcode): JsonResponse
    {
        $user = $request->user();

        $food = $user?->foods()
            ->where('barcode', $barcode)
            ->first();

        if ($food instanceof Food) {
            return response()->json([
                'source' => 'library',
                'food' => [
                    'id' => $food->id,
                    'name' => $food->name,
                    'barcode' => $food->barcode,
                    'serving_size' => (float) $food->serving_size,
                    'serving_unit' => $food->serving_unit,
                    'calories_per_serving' => (float) $food->calories_per_serving,
                    'protein_grams' => (float) $food->protein_grams,
                    'carb_grams' => (float) $food->carb_grams,
                    'fat_grams' => (float) $food->fat_grams,
                    'default_quantity' => 1.0,
                    'reference_quantity' => (float) $food->serving_size,
                ],
            ]);
        }

        $lookupResult = $lookup->lookup($barcode);

        if ($lookupResult !== null) {
            return response()->json([
                'source' => 'external',
                'food' => [
                    'id' => null,
                    'name' => $lookupResult['name'] ?? null,
                    'barcode' => $barcode,
                    'serving_size' => (float) ($lookupResult['serving_size'] ?? 1),
                    'serving_unit' => $lookupResult['serving_unit'] ?? 'serving',
                    'servings' => (float) ($lookupResult['servings'] ?? 1),
                    'calories_total' => (float) ($lookupResult['calories'] ?? 0),
                    'protein_total' => (float) ($lookupResult['protein'] ?? 0),
                    'carb_total' => (float) ($lookupResult['carb'] ?? 0),
                    'fat_total' => (float) ($lookupResult['fat'] ?? 0),
                    'calories_per_serving' => (float) ($lookupResult['calories'] ?? 0),
                    'protein_grams' => (float) ($lookupResult['protein'] ?? 0),
                    'carb_grams' => (float) ($lookupResult['carb'] ?? 0),
                    'fat_grams' => (float) ($lookupResult['fat'] ?? 0),
                    'default_quantity' => (float) ($lookupResult['default_quantity'] ?? 1),
                    'reference_quantity' => (float) ($lookupResult['reference_quantity'] ?? $lookupResult['serving_size'] ?? 0),
                ],
            ]);
        }

        return response()->json(['message' => 'Food not found.'], 404);
    }
}
