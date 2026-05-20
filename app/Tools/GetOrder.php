<?php

namespace App\Tools;

use App\Services\CsvService;

class GetOrder
{
    protected CsvService $csvService;

    public function __construct()
    {
        $this->csvService = new CsvService();
    }

    /**
     * Get order by order_id
     */
    public function execute(string $orderId): ?array
    {
        $order = $this->csvService
            ->getOrders()
            ->firstWhere('order_id', $orderId);

        if (!$order) {
            return null;
        }

        return [

            'order_id' => $order['order_id'],

            'order_date' => $order['order_date'],

            'product_id' => $order['product_id'],

            'size' => $order['size'],

            'price_paid' => $order['price_paid'],

            'customer_id' => $order['customer_id'],
        ];
    }
}