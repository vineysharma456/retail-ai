<?php

namespace App\Tools;

use App\Services\CsvService;

class GetProduct
{
    protected CsvService $csvService;

    public function __construct()
    {
        $this->csvService = new CsvService();
    }

    /**
     * Get single product by product_id
     */
    public function execute(string $productId): ?array
    {
        $product = $this->csvService
            ->getProducts()
            ->firstWhere('product_id', $productId);

        if (!$product) {
            return null;
        }

        return [

            'product_id' => $product['product_id'],

            'title' => $product['title'],

            'vendor' => $product['vendor'],

            'price' => $product['price'],

            'compare_at_price' => $product['compare_at_price'],

            'tags' => $product['tags_array'],

            'sizes_available' => explode('|', $product['sizes_available']),

            'stock_map' => $product['stock_map'],

            'is_sale' => $product['is_sale'],

            'is_clearance' => $product['is_clearance'],

            'bestseller_score' => $product['bestseller_score'],
        ];
    }
}