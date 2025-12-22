<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderItemResource for order item view
 */
class OrderItemResource extends JsonResource
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
            'product_title' => $product?->title ?? 'Product #'.$this->product_id,
            'product_image_base64' => $this->getMainImageBase64($product),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'purchase_type' => $this->purchase_type,
            // Resale info - return null for non-resale items instead of empty object
            'resale' => $this->isResale() ? [
                'plan_id' => $this->resale_plan_id,
                'months' => $this->resale_months,
                'profit_percentage' => $this->resale_profit_percentage,
                'expected_return' => $this->resale_expected_return,
                'profit_amount' => $this->resale_expected_return
                    ? $this->resale_expected_return - $this->total_price
                    : 0,
            ] : null,
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
}
