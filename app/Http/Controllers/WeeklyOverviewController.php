<?php

namespace App\Http\Controllers;

use App\Actions\Nutrition\BuildDashboardSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyOverviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, BuildDashboardSummary $buildDashboardSummary): Response
    {
        $user = $request->user();

        $dateInput = $request->string('date')->toString();
        $selectedDate = $this->resolveDate($dateInput);

        $summary = $buildDashboardSummary($user, $selectedDate);

        $weekly = $summary['weekly'];
        $macroGoal = $summary['macro_goal'];

        $weekStart = CarbonImmutable::createFromFormat('Y-m-d', $weekly['start'])->startOfDay();
        $weekEnd = CarbonImmutable::createFromFormat('Y-m-d', $weekly['end'])->endOfDay();

        $foodEntries = $user->foodEntries()
            ->whereDate('consumed_on', '>=', $weekStart->toDateString())
            ->whereDate('consumed_on', '<=', $weekEnd->toDateString())
            ->orderBy('consumed_on')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'calories',
                'protein_grams',
                'carb_grams',
                'fat_grams',
                'quantity',
                'serving_unit',
                'consumed_on',
            ])->map(fn ($entry) => [
                'id' => $entry->id,
                'name' => $entry->name,
                'calories' => round((float) $entry->calories, 2),
                'protein' => round((float) $entry->protein_grams, 2),
                'carb' => round((float) $entry->carb_grams, 2),
                'fat' => round((float) $entry->fat_grams, 2),
                'quantity' => (float) $entry->quantity,
                'serving_unit' => $entry->serving_unit,
                'consumed_on' => $entry->consumed_on->toDateString(),
                'weekday' => $entry->consumed_on->format('D'),
            ])->values()->all();

        $burnEntries = $user->calorieBurnEntries()
            ->whereDate('recorded_on', '>=', $weekStart->toDateString())
            ->whereDate('recorded_on', '<=', $weekEnd->toDateString())
            ->orderBy('recorded_on')
            ->orderByDesc('calories')
            ->get([
                'id',
                'calories',
                'description',
                'recorded_on',
            ])->map(fn ($entry) => [
                'id' => $entry->id,
                'calories' => (int) $entry->calories,
                'description' => $entry->description,
                'recorded_on' => $entry->recorded_on->toDateString(),
                'weekday' => $entry->recorded_on->format('D'),
            ])->values()->all();

        return Inertia::render('Dashboard/WeeklyOverview', [
            'week' => $weekly,
            'macroGoal' => $macroGoal,
            'date' => [
                'current' => $selectedDate->toDateString(),
                'previous' => $selectedDate->subWeek()->toDateString(),
                'next' => $selectedDate->addWeek()->toDateString(),
            ],
            'foods' => $foodEntries,
            'burns' => $burnEntries,
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
