<?php

namespace App\Services\Nutrition;

use Illuminate\Support\Arr;

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

        $limit = max(1, min($limit, 20));

        try {
            $products = $this->client->find($query);
        } catch (\Throwable) {
            return [];
        }

        $results = [];

        foreach ($products->take($limit) as $product) {
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
