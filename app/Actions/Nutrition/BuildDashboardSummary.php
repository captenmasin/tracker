<?php

namespace App\Actions\Nutrition;

use App\Models\MacroGoal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildDashboardSummary
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $user, CarbonImmutable $forDate): array
    {
        $selectedDate = $forDate->toDateString();

        $foodEntries = $user->foodEntries()
            ->whereDate('consumed_on', $selectedDate)
            ->orderByDesc('consumed_on')
            ->orderByDesc('created_at')
            ->get();

        $burnEntries = $user->calorieBurnEntries()
            ->whereDate('recorded_on', $selectedDate)
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->get();

        $dailyTotals = [
            'calories' => (float) $foodEntries->sum('calories'),
            'protein' => (float) $foodEntries->sum('protein_grams'),
            'carb' => (float) $foodEntries->sum('carb_grams'),
            'fat' => (float) $foodEntries->sum('fat_grams'),
        ];

        $dailyBurned = (float) $burnEntries->sum('calories');
        $dailyNet = $dailyTotals['calories'] - $dailyBurned;

        $macroGoal = $user->macroGoal;
        $macroTargets = $macroGoal?->macroGramTargets();
        $macroAllowances = $this->macroAllowances($macroGoal, $dailyBurned);

        $macroProgress = collect([
            'protein' => $dailyTotals['protein'],
            'carb' => $dailyTotals['carb'],
            'fat' => $dailyTotals['fat'],
        ])->map(function (float $consumed, string $key) use ($macroTargets, $macroAllowances): array {
            $allowance = $macroAllowances[$key] ?? 0.0;
            $baseGoal = $macroTargets[$key] ?? null;
            $goal = $baseGoal !== null ? round($baseGoal + $allowance, 2) : null;
            $remaining = $goal !== null ? max(round($goal - $consumed, 2), 0) : null;
            $percentage = $goal !== null && $goal > 0
                ? min(round(($consumed / $goal) * 100, 1), 999.9)
                : null;

            return [
                'consumed' => round($consumed, 2),
                'goal' => $goal,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'allowance' => round($allowance, 2),
            ];
        })->all();

        $weekRange = $this->weekRange($forDate);
        $weeklyFoodTotals = $this->aggregateFoodEntries($user, $weekRange['start'], $weekRange['end']);
        $weeklyBurnTotals = $this->aggregateBurnEntries($user, $weekRange['start'], $weekRange['end']);

        $weeklyDays = [];
        $weeklyTotals = [
            'calories' => 0.0,
            'burned' => 0.0,
            'net' => 0.0,
            'protein' => 0.0,
            'carb' => 0.0,
            'fat' => 0.0,
        ];

        for ($day = $weekRange['start']; $day->lte($weekRange['end']); $day = $day->addDay()) {
            $date = $day->toDateString();
            $dailyFood = $weeklyFoodTotals->get($date, [
                'calories' => 0.0,
                'protein' => 0.0,
                'carb' => 0.0,
                'fat' => 0.0,
            ]);
            $dailyBurn = $weeklyBurnTotals->get($date, 0.0);
            $dailyNetWeek = $dailyFood['calories'] - $dailyBurn;
            $weeklyDays[] = [
                'date' => $date,
                'weekday' => $day->format('D'),
                'calories' => round($dailyFood['calories'], 2),
                'burned' => round($dailyBurn, 2),
                'net' => round($dailyNetWeek, 2),
                'macros' => [
                    'protein' => round($dailyFood['protein'], 2),
                    'carb' => round($dailyFood['carb'], 2),
                    'fat' => round($dailyFood['fat'], 2),
                ],
            ];

            $weeklyTotals['calories'] += $dailyFood['calories'];
            $weeklyTotals['burned'] += $dailyBurn;
            $weeklyTotals['net'] += $dailyNetWeek;
            $weeklyTotals['protein'] += $dailyFood['protein'];
            $weeklyTotals['carb'] += $dailyFood['carb'];
            $weeklyTotals['fat'] += $dailyFood['fat'];
        }

        $weeklyTotals = array_map(fn (float $value): float => round($value, 2), $weeklyTotals);

        return [
            'date' => [
                'current' => $selectedDate,
                'display' => $forDate->translatedFormat('F j, Y'),
                'previous' => $forDate->subDay()->toDateString(),
                'next' => $forDate->addDay()->toDateString(),
                'isToday' => $forDate->isSameDay(CarbonImmutable::now($forDate->getTimezone())),
            ],
            'calories' => [
                'consumed' => round($dailyTotals['calories'], 2),
                'burned' => round($dailyBurned, 2),
                'net' => round($dailyNet, 2),
                'goal' => $macroGoal?->daily_calorie_goal,
                'remaining' => $macroGoal !== null
                    ? max(round($macroGoal->daily_calorie_goal - $dailyNet, 2), 0)
                    : null,
            ],
            'macros' => $macroProgress,
            'entries' => [
                'foods' => $foodEntries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'name' => $entry->name,
                    'quantity' => (float) $entry->quantity,
                    'serving_unit' => $entry->serving_unit,
                    'calories' => round((float) $entry->calories, 2),
                    'protein_grams' => round((float) $entry->protein_grams, 2),
                    'carb_grams' => round((float) $entry->carb_grams, 2),
                    'fat_grams' => round((float) $entry->fat_grams, 2),
                    'consumed_on' => $entry->consumed_on->toDateString(),
                    'source' => $entry->source,
                    'barcode' => $entry->barcode,
                ])->all(),
                'burns' => $burnEntries->map(fn ($entry) => [
                    'id' => $entry->id,
                    'calories' => (int) $entry->calories,
                    'description' => $entry->description,
                    'recorded_on' => $entry->recorded_on->toDateString(),
                ])->all(),
            ],
            'macro_goal' => $macroGoal ? [
                'daily_calorie_goal' => $macroGoal->daily_calorie_goal,
                'protein_percentage' => $macroGoal->protein_percentage,
                'carb_percentage' => $macroGoal->carb_percentage,
                'fat_percentage' => $macroGoal->fat_percentage,
                'targets' => $macroTargets,
            ] : null,
            'weekly' => [
                'start' => $weekRange['start']->toDateString(),
                'end' => $weekRange['end']->toDateString(),
                'days' => $weeklyDays,
                'totals' => $weeklyTotals,
            ],
        ];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    protected function weekRange(CarbonImmutable $date): array
    {
        $end = $date->endOfDay();
        $start = $date->subDays(6)->startOfDay();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @return Collection<string, array<string, float>>
     */
    protected function aggregateFoodEntries(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $user->foodEntries()
            ->selectRaw('consumed_on as date, sum(calories) as calories, sum(protein_grams) as protein, sum(carb_grams) as carb, sum(fat_grams) as fat')
            ->whereDate('consumed_on', '>=', $start->toDateString())
            ->whereDate('consumed_on', '<=', $end->toDateString())
            ->groupBy('consumed_on')
            ->get()
            ->mapWithKeys(fn ($row) => [
                CarbonImmutable::parse($row->date)->toDateString() => [
                    'calories' => (float) $row->calories,
                    'protein' => (float) $row->protein,
                    'carb' => (float) $row->carb,
                    'fat' => (float) $row->fat,
                ],
            ]);
    }

    /**
     * @return Collection<string, float>
     */
    protected function aggregateBurnEntries(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $user->calorieBurnEntries()
            ->selectRaw('recorded_on as date, sum(calories) as calories')
            ->whereDate('recorded_on', '>=', $start->toDateString())
            ->whereDate('recorded_on', '<=', $end->toDateString())
            ->groupBy('recorded_on')
            ->get()
            ->mapWithKeys(fn ($row) => [
                CarbonImmutable::parse($row->date)->toDateString() => (float) $row->calories,
            ]);
    }

    /**
     * @return array<string, float>
     */
    protected function macroAllowances(?MacroGoal $macroGoal, float $burnCalories): array
    {
        if ($macroGoal === null || $burnCalories <= 0) {
            return [
                'protein' => 0.0,
                'carb' => 0.0,
                'fat' => 0.0,
            ];
        }

        return [
            'protein' => round(($burnCalories * ($macroGoal->protein_percentage / 100)) / 4, 2),
            'carb' => round(($burnCalories * ($macroGoal->carb_percentage / 100)) / 4, 2),
            'fat' => round(($burnCalories * ($macroGoal->fat_percentage / 100)) / 9, 2),
        ];
    }
}
