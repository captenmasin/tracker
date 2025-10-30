<?php

namespace App\Services\Nutrition;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class BarcodeLookup
{
    /**
     * @return array<string, mixed>|null
     */
    public function lookup(string $barcode): ?array
    {
        $endpoint = (string) config('services.nutrition.endpoint');

        if ($endpoint === '') {
            return null;
        }

        $uri = rtrim($endpoint, '/').'/'.urlencode($barcode).'.json';

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->get($uri, [
                'fields' => 'product_name,serving_size,serving_quantity,product_quantity,product_quantity_unit,nutriments',
            ]);

        if ($response->failed()) {
            return null;
        }

        $product = Arr::get($response->json(), 'product');

        if (! is_array($product)) {
            return null;
        }

        $totalQuantity = $this->resolveTotalQuantity($product);
        $servingQuantity = $this->resolveServingQuantity($product);
        $unit = $this->resolveUnit($product, $totalQuantity ?: $servingQuantity);

        $servingCount = ($totalQuantity > 0 && $servingQuantity > 0)
            ? $totalQuantity / $servingQuantity
            : null;

        $nutriments = Arr::get($product, 'nutriments', []);

        $caloriesTotal = $this->resolveTotalNutriment(
            $nutriments,
            'energy-kcal',
            $totalQuantity,
            $servingQuantity,
            $servingCount,
            $unit
        );

        $proteinTotal = $this->resolveTotalNutriment(
            $nutriments,
            'proteins',
            $totalQuantity,
            $servingQuantity,
            $servingCount,
            $unit
        );

        $carbTotal = $this->resolveTotalNutriment(
            $nutriments,
            'carbohydrates',
            $totalQuantity,
            $servingQuantity,
            $servingCount,
            $unit
        );

        $fatTotal = $this->resolveTotalNutriment(
            $nutriments,
            'fat',
            $totalQuantity,
            $servingQuantity,
            $servingCount,
            $unit
        );

        $referenceQuantity = $totalQuantity > 0
            ? $totalQuantity
            : ($servingQuantity > 0 ? $servingQuantity : 1.0);

        return [
            'name' => Arr::get($product, 'product_name'),
            'serving_size' => round($referenceQuantity, 2),
            'serving_unit' => $unit,
            'servings' => $servingCount !== null && $servingCount > 0
                ? round($servingCount, 2)
                : 1.0,
            'calories' => $caloriesTotal,
            'protein' => $proteinTotal,
            'carb' => $carbTotal,
            'fat' => $fatTotal,
            'default_quantity' => 1.0,
            'reference_quantity' => round($referenceQuantity, 2),
            'serving_quantity' => round($servingQuantity ?: $referenceQuantity, 2),
            'total_quantity' => round(max($totalQuantity, $servingQuantity ?: $referenceQuantity), 2),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $headers = [];

        $token = (string) config('services.nutrition.key', '');
        $headerName = (string) config('services.nutrition.key_header', 'Authorization');

        if ($token !== '') {
            $headers[$headerName] = $headerName === 'Authorization'
                ? 'Bearer '.$token
                : $token;
        }

        $headers['User-Agent'] = sprintf('%s (Laravel Calorie Tracker)', config('app.name', 'Calorie Tracker'));

        return $headers;
    }

    protected function parseNumeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);

            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveTotalQuantity(array $product): float
    {
        $quantity = $this->parseNumeric(Arr::get($product, 'product_quantity'));

        if ($quantity > 0) {
            return $this->normalizeQuantity($quantity, Arr::get($product, 'product_quantity_unit'));
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveServingQuantity(array $product): float
    {
        $servingQuantity = $this->parseNumeric(Arr::get($product, 'serving_quantity'));

        if ($servingQuantity > 0) {
            return $this->normalizeQuantity($servingQuantity, Arr::get($product, 'serving_quantity_unit'));
        }

        if (is_string($servingSize = Arr::get($product, 'serving_size')) &&
            preg_match('/([\d.,]+)\s*([\p{L}%]+)/u', $servingSize, $matches)) {
            $size = $this->parseNumeric($matches[1]);

            return $this->normalizeQuantity($size, $matches[2] ?? null);
        }

        return 0.0;
    }

    protected function normalizeQuantity(float $quantity, mixed $unit): float
    {
        $unit = is_string($unit) ? strtolower(trim($unit)) : '';

        return match ($unit) {
            'kg' => $quantity * 1000,
            'g' => $quantity,
            'l' => $quantity * 1000,
            'ml' => $quantity,
            'cl' => $quantity * 10,
            'dl' => $quantity * 100,
            default => $quantity,
        };
    }

    /**
     * @param  array<string, mixed>  $product
     */
    protected function resolveUnit(array $product, float $referenceQuantity): string
    {
        $unit = strtolower((string) Arr::get($product, 'product_quantity_unit', ''));

        $unit = match ($unit) {
            'kg', 'g' => 'g',
            'l', 'ml', 'cl', 'dl' => 'ml',
            default => '',
        };

        if ($unit !== '') {
            return $unit;
        }

        if ($referenceQuantity > 0 && $referenceQuantity >= 1000) {
            return 'ml';
        }

        if (is_string($servingSize = Arr::get($product, 'serving_size')) &&
            preg_match('/([\p{L}%]+)/u', $servingSize, $matches)) {
            $parsedUnit = strtolower(trim($matches[1]));

            return match ($parsedUnit) {
                'kg', 'g' => 'g',
                'l', 'ml', 'cl', 'dl' => 'ml',
                default => 'g',
            };
        }

        return 'g';
    }

    /**
     * @param  array<string, mixed>  $nutriments
     */
    protected function resolveTotalNutriment(
        array $nutriments,
        string $key,
        float $totalQuantity,
        float $servingQuantity,
        ?float $servingCount,
        string $unit
    ): float {
        $servingValue = Arr::get($nutriments, $key.'_serving');

        if (is_numeric($servingValue)) {
            if ($servingCount !== null && $servingCount > 0) {
                return round((float) $servingValue * $servingCount, 2);
            }

            if ($servingQuantity > 0 && $totalQuantity > 0) {
                $calculatedServings = $totalQuantity / $servingQuantity;

                if ($calculatedServings > 0) {
                    return round((float) $servingValue * $calculatedServings, 2);
                }
            }

            return round((float) $servingValue, 2);
        }

        $per100Key = $unit === 'ml'
            ? $key.'_100ml'
            : $key.'_100g';

        $per100 = Arr::get($nutriments, $per100Key);

        if (is_numeric($per100)) {
            if ($totalQuantity > 0) {
                return round(((float) $per100) * ($totalQuantity / 100), 2);
            }

            return round((float) $per100, 2);
        }

        $fallback = Arr::get($nutriments, $key.'_100g') ?? Arr::get($nutriments, $key.'_100ml');

        if (is_numeric($fallback)) {
            if ($totalQuantity > 0) {
                return round(((float) $fallback) * ($totalQuantity / 100), 2);
            }

            return round((float) $fallback, 2);
        }

        return 0.0;
    }
}
