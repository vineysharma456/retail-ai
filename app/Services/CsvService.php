<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CsvService
{
    protected string $productsPath;
    protected string $ordersPath;
    protected string $policyPath;

    public function __construct()
    {
        $this->productsPath = storage_path('app/data/product_inventory.csv');
        $this->ordersPath = storage_path('app/data/orders.csv');
        $this->policyPath = storage_path('app/data/policy.txt');
    }

      /**
     * Get all products
     */
    public function getProducts(): Collection
    {
        return collect($this->readCsv($this->productsPath))
            ->map(function ($product) {

                // Convert stock_per_size string into array
                // Example:
                // "8:5,10:2"
                // becomes:
                // [8 => 5, 10 => 2]

                $stockMap = [];
                if (!empty($product['stock_per_size'])) {

                    /*
                    |--------------------------------------------------------------------------
                    | Convert Python-style dictionary string to JSON
                    |--------------------------------------------------------------------------
                    | Example:
                    | {'10':13,'14':19,'8':17}
                    |--------------------------------------------------------------------------
                    */

                    $json = str_replace("'", '"', $product['stock_per_size']);

                    $decoded = json_decode($json, true);

                    if (is_array($decoded)) {

                        $stockMap = $decoded;
                    }
                }

                $product['stock_map'] = $stockMap;

                // Convert tags into array
                $product['tags_array'] = array_map(
                    'trim',
                    explode(',', $product['tags'])
                );

                // Convert booleans
                $product['is_sale'] = filter_var($product['is_sale'], FILTER_VALIDATE_BOOLEAN);
                $product['is_clearance'] = filter_var($product['is_clearance'], FILTER_VALIDATE_BOOLEAN);

                // Convert numbers
                $product['price'] = (float) $product['price'];
                $product['compare_at_price'] = (float) $product['compare_at_price'];
                $product['bestseller_score'] = (int) $product['bestseller_score'];

                return $product;
            });
    }


        /**
     * Get all orders
     */
    public function getOrders(): Collection
    {
        return collect($this->readCsv($this->ordersPath))
            ->map(function ($order) {

                $order['price_paid'] = (float) $order['price_paid'];

                return $order;
            });
    }

    /**
     * Get policy text
     */
    public function getPolicyText(): string
    {
        return file_get_contents($this->policyPath);
    }

    /**
     * Read CSV file
     */
    protected function readCsv(string $path): array
    {
        $rows = [];

        if (!file_exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {

            $rows[] = array_combine($headers, $row);
        }

        fclose($handle);

        return $rows;
    }

}   