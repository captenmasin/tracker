<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodEntry extends Model
{
    /** @use HasFactory<\Database\Factories\FoodEntryFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FOOD = 'food';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'food_id',
        'name',
        'barcode',
        'consumed_on',
        'quantity',
        'serving_unit',
        'calories',
        'protein_grams',
        'carb_grams',
        'fat_grams',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_on' => 'date',
            'quantity' => 'float',
            'calories' => 'float',
            'protein_grams' => 'float',
            'carb_grams' => 'float',
            'fat_grams' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
