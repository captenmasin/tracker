<?php

use App\Http\Controllers\CalorieBurnEntryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodBarcodeController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\FoodEntryController;
use App\Http\Controllers\FoodSearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('foods', FoodController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('food-entries', FoodEntryController::class)
        ->only(['store', 'destroy']);

    Route::resource('calorie-burn-entries', CalorieBurnEntryController::class)
        ->only(['store', 'destroy']);

    Route::get('foods/search', FoodSearchController::class)->name('foods.search');

    Route::get('foods/barcode/{barcode}', FoodBarcodeController::class)
        ->where('barcode', '[A-Za-z0-9\\-]+')
        ->name('foods.barcode');
});

require __DIR__.'/settings.php';
