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
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            // Shipping info for sale orders
            'shipping' => $this->when($this->isSale(), [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'city' => $this->shipping_city,
                'address' => $this->shipping_address,
            ]),
            // Resale info
            'resale' => $this->when($this->isResale(), [
                'return_date' => $this->resale_return_date?->format('Y-m-d'),
                'expected_return' => $this->resale_expected_return,
                'profit_amount' => $this->resale_expected_return ? $this->resale_expected_return - $this->total_amount : null,
                'returned' => $this->resale_returned,
                'returned_at' => $this->resale_returned_at,
            ]),
            'items' => OrderItemResource::collection($this->items),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
