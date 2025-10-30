<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    /** @use HasFactory<\Database\Factories\FoodFactory> */
    use HasFactory;

    protected $table = 'foods';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'barcode',
        'serving_size',
        'serving_unit',
        'calories_per_serving',
        'protein_grams',
        'carb_grams',
        'fat_grams',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serving_size' => 'float',
            'calories_per_serving' => 'float',
            'protein_grams' => 'float',
            'carb_grams' => 'float',
            'fat_grams' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FoodEntry::class);
    }
}
