<?php

namespace App\Tools;

use App\Services\CsvService;
use Illuminate\Support\Collection;

class SearchProducts
{
    protected CsvService $csvService;

    public function __construct()
    {
        $this->csvService = new CsvService();
    }

    /**
     * Search products using intelligent filtering
     */
    public function execute(array $filters = []): Collection
    {
        $products = $this->csvService->getProducts();

        /*
        |--------------------------------------------------------------------------
        | Normalize Filters
        |--------------------------------------------------------------------------
        */
        $requestedSize = null;

        if (!empty($filters['size'])) {

            $requestedSize = (string) $filters['size'];

            /*
            |--------------------------------------------------------------------------
            | Convert numeric sizes to string format if needed
            |--------------------------------------------------------------------------
            | CSV stock keys are strings like:
            | "2", "4", "6", "8", "10"
            |--------------------------------------------------------------------------
            */
            $requestedSize = trim($requestedSize);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by max price
        |--------------------------------------------------------------------------
        */
        if (!empty($filters['max_price'])) {

            $maxPrice = (float) $filters['max_price'];

            $products = $products->filter(function ($product) use ($maxPrice) {

                return (float) $product['price'] <= $maxPrice;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by sale items
        |--------------------------------------------------------------------------
        */
        if (!empty($filters['sale_only'])) {

            $products = $products->filter(function ($product) {

                return (bool) $product['is_sale'] === true;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Exclude clearance by default
        |--------------------------------------------------------------------------
        */
        if (empty($filters['include_clearance'])) {

            $products = $products->filter(function ($product) {

                return (bool) $product['is_clearance'] === false;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Intelligent Tag Filtering
        |--------------------------------------------------------------------------
        */
        if (!empty($filters['tags'])) {

            $tags = array_map(function ($tag) {

                return strtolower(trim($tag));

            }, $filters['tags']);

            $products = $products->filter(function ($product) use ($tags) {

                $productTags = array_map(function ($tag) {

                    return strtolower(trim($tag));

                }, $product['tags_array']);

                $matchedCount = 0;

                foreach ($tags as $tag) {

                    foreach ($productTags as $productTag) {

                        /*
                        |--------------------------------------------------------------------------
                        | Exact Match
                        |--------------------------------------------------------------------------
                        */
                        if ($tag === $productTag) {

                            $matchedCount++;
                            break;
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Partial Match
                        |--------------------------------------------------------------------------
                        */
                        if (
                            str_contains($productTag, $tag) ||
                            str_contains($tag, $productTag)
                        ) {

                            $matchedCount++;
                            break;
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Require at least ONE matching tag
                |--------------------------------------------------------------------------
                */
                return $matchedCount > 0;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter by size + stock
        |--------------------------------------------------------------------------
        */
        if (!empty($requestedSize)) {

            $products = $products->filter(function ($product) use ($requestedSize) {

                /*
                |--------------------------------------------------------------------------
                | Debug structure example:
                | [
                |   "6" => 14,
                |   "8" => 10,
                |   "10" => 6
                | ]
                |--------------------------------------------------------------------------
                */

                if (!isset($product['stock_map'][$requestedSize])) {

                    return false;
                }

                return (int) $product['stock_map'][$requestedSize] > 0;
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Intelligent Ranking
        |--------------------------------------------------------------------------
        */
        $products = $products->map(function ($product) use ($filters) {

            $score = 0;

            /*
            |--------------------------------------------------------------------------
            | Bestseller priority
            |--------------------------------------------------------------------------
            */
            $score += (int) $product['bestseller_score'];

            /*
            |--------------------------------------------------------------------------
            | Sale boost
            |--------------------------------------------------------------------------
            */
            if ((bool) $product['is_sale']) {

                $score += 50;
            }

            /*
            |--------------------------------------------------------------------------
            | Clearance penalty
            |--------------------------------------------------------------------------
            */
            if ((bool) $product['is_clearance']) {

                $score -= 100;
            }

            /*
            |--------------------------------------------------------------------------
            | Tag relevance boost
            |--------------------------------------------------------------------------
            */
            if (!empty($filters['tags'])) {

                $productTags = array_map(
                    'strtolower',
                    $product['tags_array']
                );

                foreach ($filters['tags'] as $tag) {

                    $tag = strtolower(trim($tag));

                    if (in_array($tag, $productTags)) {

                        $score += 20;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Higher stock gets bonus
            |--------------------------------------------------------------------------
            */
            $totalStock = array_sum($product['stock_map']);

            $score += min($totalStock, 20);

            $product['recommendation_score'] = $score;

            return $product;
        });

        /*
        |--------------------------------------------------------------------------
        | Sort by recommendation score
        |--------------------------------------------------------------------------
        */
        $products = $products->sortByDesc('recommendation_score');

        /*
        |--------------------------------------------------------------------------
        | Return top 5 results
        |--------------------------------------------------------------------------
        */
        return $products
            ->values()
            ->take(5)
            ->map(function ($product) {

                return [

                    'product_id' => $product['product_id'],

                    'title' => $product['title'],

                    'vendor' => $product['vendor'],

                    'price' => $product['price'],

                    'compare_at_price' => $product['compare_at_price'],

                    'is_sale' => (bool) $product['is_sale'],

                    'is_clearance' => (bool) $product['is_clearance'],

                    'bestseller_score' => $product['bestseller_score'],

                    'recommendation_score' => $product['recommendation_score'],

                    'tags' => $product['tags_array'],

                    'available_sizes' => explode(
                        '|',
                        $product['sizes_available']
                    ),

                    'stock_map' => $product['stock_map'],
                ];
            });
    }
}