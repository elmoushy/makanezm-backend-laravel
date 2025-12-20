<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource for list view (minimal data)
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get authenticated user to check favorite status
        $user = $request->user();

        // Build installment options from resale_plans (for frontend compatibility)
        $installmentOptions = [];
        if ($this->relationLoaded('resalePlans')) {
            $installmentOptions = $this->resalePlans->map(fn ($plan) => [
                'months' => $plan->months,
                'percentage' => $plan->profit_percentage,
            ])->values()->toArray();
        }

        return [
            'id' => $this->id,
            // Bilingual fields (backend naming)
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            // Frontend compatibility aliases
            'name' => $this->title_en,
            'nameAr' => $this->title_ar,
            'description' => $this->description_en,
            'descriptionAr' => $this->description_ar,
            // Category (type in backend = category in frontend)
            'type' => $this->type,
            'category' => $this->type,
            // Pricing
            'price' => (float) $this->price,
            // Stock
            'stock_quantity' => $this->stock_quantity,
            'stock' => $this->stock_quantity,
            'max_stock' => $this->getDynamicMaxStock(),
            'in_stock' => $this->in_stock,
            // Stock visualization helpers
            'stock_percentage' => $this->calculateStockPercentage(),
            'stock_status' => $this->getStockStatus(),
            // Status
            'is_active' => $this->is_active,
            'isVisible' => $this->is_active,
            'approvalStatus' => $this->is_active ? 'approved' : 'pending',
            // Featured & Order
            'is_featured' => $this->is_featured,
            'isFeatured' => $this->is_featured,
            'display_order' => $this->display_order,
            'displayOrder' => $this->display_order,
            // Images
            'main_image_url' => $this->main_image ? route('products.main-image', $this->id) : null,
            'main_image_base64' => $this->getMainImageBase64(),
            'image' => $this->main_image ? route('products.main-image', $this->id) : null,
            'is_favorited' => $this->isFavoritedBy($user),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => route('products.image', ['productId' => $this->id, 'imageId' => $image->id]),
                    'sort_order' => $image->sort_order,
                ]);
            }),
            // Payment options
            'payment_options' => $this->whenLoaded('paymentOptions', function () {
                return $this->paymentOptions->map(fn ($option) => [
                    'id' => $option->id,
                    'type' => $option->type,
                    'label' => $option->getDisplayLabel(),
                    'is_active' => $option->is_active,
                ]);
            }),
            // Resale plans (backend format)
            'resale_plans' => $this->whenLoaded('resalePlans', function () {
                return $this->resalePlans->map(fn ($plan) => [
                    'id' => $plan->id,
                    'months' => $plan->months,
                    'profit_percentage' => $plan->profit_percentage,
                    'label' => $plan->getDisplayLabel(),
                    'is_active' => $plan->is_active,
                ]);
            }),
            // Installment options (frontend format)
            'installmentOptions' => $installmentOptions,
            'allowInstallment' => count($installmentOptions) > 0,
            // Timestamps
            'created_at' => $this->created_at,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get main image as Base64 encoded string.
     */
    protected function getMainImageBase64(): ?string
    {
        if (! $this->main_image) {
            return null;
        }

        $mimeType = $this->main_image_mime_type ?? 'image/jpeg';

        return 'data:'.$mimeType.';base64,'.base64_encode($this->main_image);
    }

    /**
     * Calculate the dynamic max stock range.
     * Rounds up to the nearest 100, with a minimum of 100.
     * Examples: 50 → 100, 130 → 200, 250 → 300, 483 → 500
     */
    protected function getDynamicMaxStock(): int
    {
        $currentStock = $this->stock_quantity ?? 0;
        
        // If stock is 0, return 100 as default max
        if ($currentStock <= 0) {
            return 100;
        }
        
        // Round up to the nearest 100
        // E.g., 1-100 → 100, 101-200 → 200, 201-300 → 300, etc.
        return (int) ceil($currentStock / 100) * 100;
    }

    /**
     * Calculate stock percentage based on current stock vs dynamic max.
     * Returns a value between 0 and 100.
     */
    protected function calculateStockPercentage(): int
    {
        $currentStock = $this->stock_quantity ?? 0;
        $maxStock = $this->getDynamicMaxStock();

        if ($maxStock <= 0) {
            return 0;
        }

        $percentage = ($currentStock / $maxStock) * 100;

        return (int) min(100, max(0, $percentage));
    }

    /**
     * Get stock status for visual indicators.
     * Returns: 'high' (>50%), 'medium' (20-50%), 'low' (<20%), 'out' (0)
     */
    protected function getStockStatus(): string
    {
        if (! $this->in_stock || $this->stock_quantity <= 0) {
            return 'out';
        }

        $percentage = $this->calculateStockPercentage();

        if ($percentage > 50) {
            return 'high';
        } elseif ($percentage >= 20) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}

