<?php

use App\Models\CalorieBurnEntry;
use App\Models\Food;
use App\Models\FoodEntry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clear', function () {
    Food::truncate();
    FoodEntry::truncate();
    CalorieBurnEntry::truncate();
});
