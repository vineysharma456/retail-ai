<?php

namespace App\Tools;

use Carbon\Carbon;

class EvaluateReturn
{
    protected GetOrder $getOrder;
    protected GetProduct $getProduct;

    public function __construct()
    {
        $this->getOrder = new GetOrder();
        $this->getProduct = new GetProduct();
    }

    /**
     * Evaluate return eligibility
     */
    public function execute(string $orderId): array
    {
        /*
        |--------------------------------------------------------------------------
        | Get Order
        |--------------------------------------------------------------------------
        */
        $order = $this->getOrder->execute($orderId);

        if (!$order) {

            return [
                'success' => false,
                'allowed' => false,
                'message' => 'Order not found.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Get Product
        |--------------------------------------------------------------------------
        */
        $product = $this->getProduct->execute($order['product_id']);

        if (!$product) {

            return [
                'success' => false,
                'allowed' => false,
                'message' => 'Product not found.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate Days Since Order
        |--------------------------------------------------------------------------
        */
        $orderDate = Carbon::parse($order['order_date']);

        $daysSincePurchase = $orderDate->diffInDays(now());

        /*
        |--------------------------------------------------------------------------
        | Clearance Items
        |--------------------------------------------------------------------------
        */
        if ($product['is_clearance']) {

            return [

                'success' => true,

                'allowed' => false,

                'refund_type' => null,

                'days_since_purchase' => $daysSincePurchase,

                'reason' => 'Clearance items are final sale and cannot be returned or exchanged.',

                'product' => $product,

                'order' => $order
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Vendor Exception: Aurelia Couture
        |--------------------------------------------------------------------------
        */
        if ($product['vendor'] === 'Aurelia Couture') {

            // Check exchange stock
            $exchangeAvailable = false;

            if (isset($product['stock_map'][$order['size']])) {

                $exchangeAvailable =
                    $product['stock_map'][$order['size']] > 0;
            }

            return [

                'success' => true,

                'allowed' => true,

                'refund_type' => 'exchange_only',

                'days_since_purchase' => $daysSincePurchase,

                'exchange_available' => $exchangeAvailable,

                'reason' => 'Aurelia Couture items are eligible for exchange only. Refunds are not allowed.',

                'product' => $product,

                'order' => $order
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Vendor Exception: Nocturne
        |--------------------------------------------------------------------------
        */
        $returnWindow = 14;

        if ($product['vendor'] === 'Nocturne') {
            $returnWindow = 21;
        }

        /*
        |--------------------------------------------------------------------------
        | Sale Items
        |--------------------------------------------------------------------------
        */
        if ($product['is_sale']) {

            if ($daysSincePurchase <= 7) {

                return [

                    'success' => true,

                    'allowed' => true,

                    'refund_type' => 'store_credit',

                    'days_since_purchase' => $daysSincePurchase,

                    'reason' => 'Sale items are returnable within 7 days for store credit only.',

                    'product' => $product,

                    'order' => $order
                ];
            }

            return [

                'success' => true,

                'allowed' => false,

                'refund_type' => null,

                'days_since_purchase' => $daysSincePurchase,

                'reason' => 'Sale item return window has expired.',

                'product' => $product,

                'order' => $order
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Normal Returns
        |--------------------------------------------------------------------------
        */
        if ($daysSincePurchase <= $returnWindow) {

            return [

                'success' => true,

                'allowed' => true,

                'refund_type' => 'full_refund',

                'days_since_purchase' => $daysSincePurchase,

                'reason' => "Return eligible within {$returnWindow}-day return window.",

                'product' => $product,

                'order' => $order
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Return Window Expired
        |--------------------------------------------------------------------------
        */
        return [

            'success' => true,

            'allowed' => false,

            'refund_type' => null,

            'days_since_purchase' => $daysSincePurchase,

            'reason' => "Return window expired after {$returnWindow} days.",

            'product' => $product,

            'order' => $order
        ];
    }
}