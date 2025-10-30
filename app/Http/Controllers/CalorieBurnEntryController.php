<?php

namespace App\Http\Controllers;

use App\Http\Requests\Nutrition\StoreCalorieBurnEntryRequest;
use App\Models\CalorieBurnEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CalorieBurnEntryController extends Controller
{
    public function store(StoreCalorieBurnEntryRequest $request): RedirectResponse
    {
        $request->user()->calorieBurnEntries()->create($request->validated());

        return back()->with('status', 'Burn logged.');
    }

    public function destroy(Request $request, CalorieBurnEntry $calorieBurnEntry): RedirectResponse
    {
        abort_if($calorieBurnEntry->user_id !== $request->user()?->id, 403);

        $calorieBurnEntry->delete();

        return back()->with('status', 'Burn entry removed.');
    }
}
