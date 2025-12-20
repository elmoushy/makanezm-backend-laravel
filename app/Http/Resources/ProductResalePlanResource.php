<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResalePlanResource for resale plans
 */
class ProductResalePlanResource extends JsonResource
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
            'months' => $this->months,
            'profit_percentage' => $this->profit_percentage,
            'label' => $this->getDisplayLabel(),
            'is_active' => $this->is_active,
            'expected_return' => $this->when(
                $this->product,
                fn () => $this->calculateExpectedReturn($this->product->price)
            ),
            'profit_amount' => $this->when(
                $this->product,
                fn () => $this->calculateProfit($this->product->price)
            ),
        ];
    }
}
