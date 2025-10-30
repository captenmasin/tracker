<?php

namespace App\Http\Controllers;

use App\Http\Requests\Nutrition\StoreFoodRequest;
use App\Http\Requests\Nutrition\UpdateFoodRequest;
use App\Models\Food;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FoodController extends Controller
{
    public function store(StoreFoodRequest $request): RedirectResponse
    {
        $data = $this->normalizePayload($request->validated());

        $request->user()->foods()->create($data);

        return back()->with('status', 'Food saved.');
    }

    public function update(UpdateFoodRequest $request, Food $food): RedirectResponse
    {
        $this->ensureOwnership($request, $food);

        $data = $this->normalizePayload($request->validated());

        $food->update($data);

        return back()->with('status', 'Food updated.');
    }

    public function destroy(Request $request, Food $food): RedirectResponse
    {
        $this->ensureOwnership($request, $food);

        $food->delete();

        return back()->with('status', 'Food removed.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        $numeric = [
            'serving_size',
            'calories_per_serving',
            'protein_grams',
            'carb_grams',
            'fat_grams',
        ];

        foreach ($numeric as $key) {
            $data[$key] = isset($data[$key]) ? (float) $data[$key] : 0.0;
        }

        $data['barcode'] = $data['barcode'] !== null ? trim((string) $data['barcode']) : null;

        return $data;
    }

    protected function ensureOwnership(Request $request, Food $food): void
    {
        abort_if($food->user_id !== $request->user()?->id, 403);
    }
}
