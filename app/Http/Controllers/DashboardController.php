<?php

namespace App\Http\Controllers;

use App\Actions\Nutrition\BuildDashboardSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BuildDashboardSummary $buildDashboardSummary): Response
    {
        $user = $request->user();

        $dateInput = $request->string('date')->toString();
        $selectedDate = $this->resolveDate($dateInput);

        $foods = $user->foods()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'barcode',
                'serving_size',
                'serving_unit',
                'calories_per_serving',
                'protein_grams',
                'carb_grams',
                'fat_grams',
            ])->map(fn ($food) => [
                'id' => $food->id,
                'name' => $food->name,
                'barcode' => $food->barcode,
                'serving_size' => (float) $food->serving_size,
                'serving_unit' => $food->serving_unit,
                'calories_per_serving' => (float) $food->calories_per_serving,
                'protein_grams' => (float) $food->protein_grams,
                'carb_grams' => (float) $food->carb_grams,
                'fat_grams' => (float) $food->fat_grams,
            ])->all();

        return Inertia::render('Dashboard', [
            'summary' => $buildDashboardSummary($user, $selectedDate),
            'foods' => $foods,
            'status' => $request->session()->get('status'),
        ]);
    }

    protected function resolveDate(?string $date): CarbonImmutable
    {
        if ($date === null) {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }
}
