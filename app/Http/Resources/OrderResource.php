<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderResource for order list view
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate resale details from items if not set on order
        $resaleExpectedReturn = $this->resale_expected_return;
        $resaleReturnDate = $this->resale_return_date;
        $totalProfitPercentage = null;

        // If order is resale type but resale_expected_return is not set, calculate from items
        if ($this->isResale() && !$resaleExpectedReturn && $this->items->isNotEmpty()) {
            $resaleItems = $this->items->filter(fn($item) => $item->isResale());

            if ($resaleItems->isNotEmpty()) {
                $resaleExpectedReturn = $resaleItems->sum('resale_expected_return');

                // Get the furthest maturity date from items
                $maxMonths = $resaleItems->max('resale_months');
                if ($maxMonths && !$resaleReturnDate) {
                    $resaleReturnDate = $this->created_at->copy()->addMonths($maxMonths);
                }

                // Calculate average profit percentage
                $totalProfitPercentage = $resaleItems->avg('resale_profit_percentage');
            }
        }

        // Include items for the order
        $items = $this->items->map(function ($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_title' => $product?->title ?? 'Product #' . $item->product_id,
                'product_image_base64' => $this->getMainImageBase64($product),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'purchase_type' => $item->purchase_type,
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
        });

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'total_amount' => $this->total_amount,
            'items_count' => $this->items->count(),
            'items' => $items,
            // Sale order info
            'shipping_city' => $this->when($this->isSale(), $this->shipping_city),
            'shipping' => $this->when($this->isSale(), [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'city' => $this->shipping_city,
                'address' => $this->shipping_address,
            ]),
            // Resale order info - calculate from items if not set
            'resale_return_date' => $this->when($this->isResale(),
                $resaleReturnDate?->format('Y-m-d')
            ),
            'resale_expected_return' => $this->when($this->isResale(), $resaleExpectedReturn),
            'resale_profit_amount' => $this->when($this->isResale(),
                $resaleExpectedReturn ? $resaleExpectedReturn - $this->total_amount : 0
            ),
            'resale_profit_percentage' => $this->when($this->isResale(), $totalProfitPercentage),
            'resale_returned' => $this->when($this->isResale(), $this->resale_returned),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get main image as Base64 encoded string.
     */
    protected function getMainImageBase64($product): ?string
    {
        if (!$product || !$product->main_image) {
            return null;
        }

        $mimeType = $product->main_image_mime_type ?? 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . base64_encode($product->main_image);
    }
}
