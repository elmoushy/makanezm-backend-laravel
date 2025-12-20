<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CartItemResource for cart view
 *
 * Returns cart item with product details including base64 image,
 * payment options (wallet), and resale plans (investment returns).
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'title' => $product?->title,
            'description' => $product?->description,
            'price' => $product?->price,
            'total_price' => $this->total_price,
            'in_stock' => $product?->in_stock,
            'stock_quantity' => $product?->stock_quantity,
            'main_image_base64' => $this->getMainImageBase64($product),
            'payment_options' => $this->getPaymentOptions($product),
            'resale_plans' => $this->getResalePlans($product),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get main image as Base64 encoded string.
     */
    protected function getMainImageBase64($product): ?string
    {
        if (! $product || ! $product->main_image) {
            return null;
        }

        $mimeType = $product->main_image_mime_type ?? 'image/jpeg';

        return 'data:'.$mimeType.';base64,'.base64_encode($product->main_image);
    }

    /**
     * Get payment options for the product.
     * Always includes wallet option for direct purchase.
     */
    protected function getPaymentOptions($product): array
    {
        if (! $product) {
            return [];
        }

        // Get payment options from product (active only)
        $options = $product->paymentOptions->map(function ($option) {
            return [
                'id' => $option->id,
                'type' => $option->type,
                'label' => $option->label,
            ];
        })->toArray();

        // If no wallet option exists, add a default one
        $hasWallet = collect($options)->contains('type', 'wallet');
        if (! $hasWallet) {
            array_unshift($options, [
                'id' => 0,
                'type' => 'wallet',
                'label' => 'Direct Purchase (Wallet)',
            ]);
        }

        return $options;
    }

    /**
     * Get resale plans (investment options) for the product.
     */
    protected function getResalePlans($product): array
    {
        if (! $product) {
            return [];
        }

        return $product->resalePlans->map(function ($plan) use ($product) {
            $basePrice = $product->price ?? 0;
            $expectedReturn = $plan->calculateExpectedReturn($basePrice);
            $profitAmount = $expectedReturn - $basePrice;

            return [
                'id' => $plan->id,
                'months' => $plan->months,
                'profit_percentage' => (float) $plan->profit_percentage,
                'label' => $plan->label,
                'expected_return' => round($expectedReturn, 2),
                'profit_amount' => round($profitAmount, 2),
            ];
        })->toArray();
    }
}
