<?php

namespace App\Http\Controllers;

use App\Http\Requests\Nutrition\StoreFoodEntryRequest;
use App\Models\Food;
use App\Models\FoodEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FoodEntryController extends Controller
{
    public function store(StoreFoodEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $quantity = (float) $validated['quantity'];
        $food = null;

        if (isset($validated['food_id'])) {
            /** @var Food $food */
            $food = $user->foods()->findOrFail($validated['food_id']);
        }

        if ($food instanceof Food) {
            $servingDescription = trim(sprintf('%s %s', $food->serving_size, $food->serving_unit));

            $entryData = [
                'food_id' => $food->id,
                'name' => $food->name,
                'barcode' => $food->barcode,
                'serving_unit' => $servingDescription !== '' ? $servingDescription : $food->serving_unit,
                'calories' => round($food->calories_per_serving * $quantity, 2),
                'protein_grams' => round($food->protein_grams * $quantity, 2),
                'carb_grams' => round($food->carb_grams * $quantity, 2),
                'fat_grams' => round($food->fat_grams * $quantity, 2),
                'source' => FoodEntry::SOURCE_FOOD,
            ];
        } else {
            $servingSizeValue = (float) ($validated['serving_size_value'] ?? 0.0);
            $servingUnitRaw = $validated['serving_unit_raw'] ?? $validated['serving_unit'] ?? 'g';
            $servingDescription = $servingSizeValue > 0
                ? trim($servingSizeValue.' '.$servingUnitRaw)
                : ($validated['serving_unit'] ?? $servingUnitRaw);

            $entryData = [
                'food_id' => null,
                'name' => $validated['name'],
                'barcode' => $validated['barcode'] ?? null,
                'serving_unit' => $servingDescription,
                'calories' => round((float) $validated['calories'], 2),
                'protein_grams' => round((float) $validated['protein_grams'], 2),
                'carb_grams' => round((float) $validated['carb_grams'], 2),
                'fat_grams' => round((float) $validated['fat_grams'], 2),
                'source' => $validated['source'] ?? FoodEntry::SOURCE_MANUAL,
            ];
        }

        $entryData['quantity'] = $quantity;
        $entryData['consumed_on'] = $validated['consumed_on'];

        $user->foodEntries()->create($entryData);

        $this->storeInLibrary($user, $entryData, $validated, $food);

        return back()->with('status', 'Entry logged.');
    }

    public function destroy(Request $request, FoodEntry $foodEntry): RedirectResponse
    {
        abort_if($foodEntry->user_id !== $request->user()?->id, 403);

        $foodEntry->delete();

        return back()->with('status', 'Entry removed.');
    }

    /**
     * @param  array<string, mixed>  $entryData
     * @param  array<string, mixed>  $validated
     */
    protected function storeInLibrary(User $user, array $entryData, array $validated, ?Food $existingFood): void
    {
        if ($existingFood instanceof Food) {
            return;
        }

        $name = $entryData['name'] ?? null;

        if ($name === null) {
            return;
        }

        $barcode = $validated['barcode'] ?? null;
        $quantity = (float) ($entryData['quantity'] ?? 1.0);
        $quantity = $quantity > 0 ? $quantity : 1.0;

        $perCalories = $quantity > 0 ? $entryData['calories'] / $quantity : $entryData['calories'];
        $perProtein = $quantity > 0 ? $entryData['protein_grams'] / $quantity : $entryData['protein_grams'];
        $perCarb = $quantity > 0 ? $entryData['carb_grams'] / $quantity : $entryData['carb_grams'];
        $perFat = $quantity > 0 ? $entryData['fat_grams'] / $quantity : $entryData['fat_grams'];

        $servingSizeValue = (float) ($validated['serving_size_value'] ?? 0.0);
        $servingUnitRaw = $validated['serving_unit_raw'] ?? null;

        if ($servingSizeValue <= 0 && isset($entryData['serving_unit'])) {
            if (preg_match('/^(\\d+(?:\.\\d+)?)\\s*(\\S+)/', (string) $entryData['serving_unit'], $matches)) {
                $servingSizeValue = (float) $matches[1];
                $servingUnitRaw = $servingUnitRaw ?? $matches[2];
            }
        }

        $servingUnitRaw = $servingUnitRaw ?? 'g';
        if ($servingSizeValue <= 0) {
            $servingSizeValue = 1.0;
        }

        $attributes = [
            'name' => $name,
            'serving_size' => round($servingSizeValue, 2),
            'serving_unit' => $servingUnitRaw,
            'calories_per_serving' => round(max($perCalories, 0), 2),
            'protein_grams' => round(max($perProtein, 0), 2),
            'carb_grams' => round(max($perCarb, 0), 2),
            'fat_grams' => round(max($perFat, 0), 2),
        ];

        $query = $user->foods();

        $food = $barcode
            ? $query->firstOrNew(['barcode' => $barcode])
            : $query->firstOrNew(['name' => $name]);

        $food->fill($attributes);

        if ($barcode !== null) {
            $food->barcode = $barcode;
        }

        $food->save();
    }
}
