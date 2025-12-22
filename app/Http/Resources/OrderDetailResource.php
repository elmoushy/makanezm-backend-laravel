<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderDetailResource for order detail view
 */
class OrderDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate resale details from items if not set on order (for mixed orders)
        $resaleExpectedReturn = $this->resale_expected_return;
        $resaleReturnDate = $this->resale_return_date;
        $resaleProfitAmount = null;

        // If order has resale items but resale_expected_return is not set, calculate from items
        if ($this->isResale() && ! $resaleExpectedReturn && $this->items->isNotEmpty()) {
            $resaleItems = $this->items->filter(fn ($item) => $item->isResale());

            if ($resaleItems->isNotEmpty()) {
                $resaleExpectedReturn = $resaleItems->sum('resale_expected_return');
                $resaleItemsTotal = $resaleItems->sum('total_price');
                $resaleProfitAmount = $resaleExpectedReturn - $resaleItemsTotal;

                // Get the furthest maturity date from items
                $maxMonths = $resaleItems->max('resale_months');
                if ($maxMonths && ! $resaleReturnDate) {
                    $resaleReturnDate = $this->created_at->copy()->addMonths($maxMonths);
                }
            }
        } elseif ($resaleExpectedReturn) {
            $resaleProfitAmount = $resaleExpectedReturn - $this->total_amount;
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            // Shipping info for sale orders - return null instead of empty object
            'shipping' => $this->isSale() && ($this->shipping_name || $this->shipping_city) ? [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'city' => $this->shipping_city,
                'address' => $this->shipping_address,
            ] : null,
            // Resale info - calculate from items for mixed orders
            'resale' => $this->isResale() ? [
                'return_date' => $resaleReturnDate?->format('Y-m-d'),
                'expected_return' => $resaleExpectedReturn,
                'profit_amount' => $resaleProfitAmount,
                'returned' => $this->resale_returned ?? false,
                'returned_at' => $this->resale_returned_at,
            ] : null,
            'items' => $this->items->map(fn ($item) => $this->transformItem($item)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Transform an order item with proper null handling for resale.
     */
    private function transformItem($item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_title' => $product?->title ?? 'Product #'.$item->product_id,
            'product_image_base64' => $this->getMainImageBase64($product),
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
            'purchase_type' => $item->purchase_type,
            // Return null for non-resale items, not empty object
            'resale' => $item->isResale() ? [
                'plan_id' => $item->resale_plan_id,
                'months' => $item->resale_months,
                'profit_percentage' => $item->resale_profit_percentage,
                'expected_return' => $item->resale_expected_return,
                'profit_amount' => $item->resale_expected_return
                    ? $item->resale_expected_return - $item->total_price
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
