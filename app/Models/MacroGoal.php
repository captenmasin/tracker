<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MacroGoal extends Model
{
    /** @use HasFactory<\Database\Factories\MacroGoalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'daily_calorie_goal',
        'protein_percentage',
        'carb_percentage',
        'fat_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_calorie_goal' => 'integer',
            'protein_percentage' => 'float',
            'carb_percentage' => 'float',
            'fat_percentage' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, float>
     */
    public function macroGramTargets(): array
    {
        $calories = $this->daily_calorie_goal;

        return [
            'protein' => round(($this->protein_percentage / 100) * $calories / 4, 2),
            'carb' => round(($this->carb_percentage / 100) * $calories / 4, 2),
            'fat' => round(($this->fat_percentage / 100) * $calories / 9, 2),
        ];
    }
}
