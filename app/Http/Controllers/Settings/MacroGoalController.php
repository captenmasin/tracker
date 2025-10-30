<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMacroGoalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MacroGoalController extends Controller
{
    public function edit(Request $request): Response
    {
        $goal = $request->user()->macroGoal;

        return Inertia::render('settings/Nutrition', [
            'macroGoal' => $goal ? [
                'daily_calorie_goal' => $goal->daily_calorie_goal,
                'protein_percentage' => $goal->protein_percentage,
                'carb_percentage' => $goal->carb_percentage,
                'fat_percentage' => $goal->fat_percentage,
            ] : null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(UpdateMacroGoalRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $request->user()->macroGoal()->updateOrCreate(
            [],
            [
                'daily_calorie_goal' => (int) $payload['daily_calorie_goal'],
                'protein_percentage' => (float) $payload['protein_percentage'],
                'carb_percentage' => (float) $payload['carb_percentage'],
                'fat_percentage' => (float) $payload['fat_percentage'],
            ],
        );

        return back()->with('status', 'Macro goals updated.');
    }
}
