<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductDetailResource for detailed view (full data)
 */
class ProductDetailResource extends JsonResource
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
        $resalePlans = $this->whenLoaded('resalePlans', $this->resalePlans) ?? collect();
        $installmentOptions = $resalePlans->map(fn ($plan) => [
            'months' => $plan->months,
            'percentage' => $plan->profit_percentage,
        ])->values()->toArray();

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
            'in_stock' => $this->in_stock,
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
            'images' => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => route('products.image', ['productId' => $this->id, 'imageId' => $image->id]),
                'base64' => $this->getImageBase64($image),
                'sort_order' => $image->sort_order,
            ]),
            // Payment options
            'payment_options' => ProductPaymentOptionResource::collection($this->whenLoaded('paymentOptions', $this->paymentOptions)),
            // Resale plans (backend format)
            'resale_plans' => ProductResalePlanResource::collection($resalePlans),
            // Installment options (frontend format)
            'installmentOptions' => $installmentOptions,
            'allowInstallment' => count($installmentOptions) > 0,
            // Favorites
            'is_favorited' => $this->isFavoritedBy($user),
            'favorites_count' => $this->favorites()->count(),
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
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

        try {
            $mimeType = $this->main_image_mime_type ?? 'image/jpeg';
            $imageData = is_resource($this->main_image)
                ? stream_get_contents($this->main_image)
                : $this->main_image;

            return 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get a sub-image as Base64 encoded string.
     */
    protected function getImageBase64($image): ?string
    {
        if (! $image->image) {
            return null;
        }

        try {
            $mimeType = $image->mime_type ?? 'image/jpeg';
            $imageData = is_resource($image->image)
                ? stream_get_contents($image->image)
                : $image->image;

            return 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        } catch (\Exception $e) {
            return null;
        }
    }
}
