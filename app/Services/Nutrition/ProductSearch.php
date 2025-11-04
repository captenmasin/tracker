<?php

namespace App\Services\Nutrition;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ProductSearch extends BarcodeLookup
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $endpoint = (string) config('services.nutrition.search_endpoint');

        if ($endpoint === '') {
            return [];
        }

        $limit = max(1, min($limit, 20));

        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->get($endpoint, array_filter([
                'search_terms' => $query,
                'page_size' => $limit,
                'fields' => 'code,product_name,product_name_en,serving_size,serving_quantity,product_quantity,product_quantity_unit,nutriments',
                'lc' => $this->language(),
                'cc' => $this->country(),
            ], fn ($value) => $value !== null && $value !== ''));

        if ($response->failed()) {
            return [];
        }

        $products = Arr::get($response->json(), 'products');

        if (! is_array($products)) {
            return [];
        }

        $results = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $normalized = $this->transformProduct($product, Arr::get($product, 'code'));

            if ($normalized === null) {
                continue;
            }

            $results[] = $normalized;
        }

        return $results;
    }
}
