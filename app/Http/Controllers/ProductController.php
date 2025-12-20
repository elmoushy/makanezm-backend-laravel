<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Product;
use App\Models\ProductFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get the authenticated user from Sanctum guard (optional).
     * Returns null if not authenticated or invalid token.
     */
    protected function getOptionalAuthUser()
    {
        try {
            return Auth::guard('sanctum')->user();
        } catch (\Exception $e) {
            return null;
        }
    }

    // ==================== Public Endpoints ====================

    /**
     * Get all active products (Public).
     * GET /api/v1/products
     *
     * This endpoint is public but optionally resolves authenticated user
     * to return favorite status for each product.
     */
    public function index(Request $request)
    {
        // Optionally resolve authenticated user for favorite status
        $user = $this->getOptionalAuthUser();

        // Set user on request for resource to access
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        $query = Product::query()
            ->with(['images', 'paymentOptions', 'resalePlans'])
            ->active();

        // Filter by type/category
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }
        if ($request->filled('category')) {
            $query->ofType($request->category);
        }

        // Filter by stock
        if ($request->filled('in_stock')) {
            $query->where('in_stock', filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title_ar', 'like', '%'.$request->search.'%')
                  ->orWhere('title_en', 'like', '%'.$request->search.'%');
            });
        }

        // Ordering
        $query->orderByDesc('is_featured')
              ->orderBy('display_order')
              ->orderByDesc('created_at');

        $products = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Get featured products for homepage (Public - Fast endpoint).
     * GET /api/v1/products/featured
     *
     * Returns exactly 3 featured/top products for the homepage.
     * Optimized for speed - no pagination, minimal data.
     */
    public function getFeaturedProducts(Request $request)
    {
        // Optionally resolve authenticated user for favorite status
        $user = $this->getOptionalAuthUser();

        // Set user on request for resource to access
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        // Get exactly 3 featured products, prioritizing is_featured flag
        $products = Product::query()
            ->with(['images', 'paymentOptions', 'resalePlans'])
            ->active()
            ->where('in_stock', true)
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'count' => $products->count(),
        ]);
    }

    /**
     * Get product details (Public).
     * GET /api/v1/products/{id}
     *
     * This endpoint is public but optionally resolves authenticated user
     * to return favorite status for the product.
     */
    public function show(Request $request, int $id)
    {
        // Optionally resolve authenticated user for favorite status
        $user = $this->getOptionalAuthUser();

        // Set user on request for resource to access
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        $product = Product::with(['images', 'paymentOptions', 'resalePlans'])
            ->active()
            ->find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        return $this->successResponse(new ProductDetailResource($product));
    }

    /**
     * Serve product main image.
     * GET /api/v1/products/{id}/main-image
     */
    public function mainImage(int $id)
    {
        $product = Product::find($id);

        if (! $product || ! $product->main_image) {
            return response('Image not found', 404);
        }

        return response($product->main_image)
            ->header('Content-Type', $product->main_image_mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve product sub-image.
     * GET /api/v1/products/{productId}/images/{imageId}
     */
    public function image(int $productId, int $imageId)
    {
        $product = Product::find($productId);

        if (! $product) {
            return response('Product not found', 404);
        }

        $image = $product->images()->find($imageId);

        if (! $image) {
            return response('Image not found', 404);
        }

        return response($image->image)
            ->header('Content-Type', $image->mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Get available product types (Public).
     * GET /api/v1/products/types
     */
    public function types()
    {
        $types = Product::active()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return $this->successResponse(['types' => $types]);
    }

    // ==================== Admin Endpoints ====================

    /**
     * Get all products including inactive (Admin).
     * GET /api/v1/admin/products
     */
    public function adminIndex(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view all products.');
        }

        $products = Product::query()
            ->with(['images', 'paymentOptions', 'resalePlans'])
            ->when($request->filled('type'), fn ($q) => $q->ofType($request->type))
            ->when($request->has('in_stock'), fn ($q) => $q->where('in_stock', filter_var($request->in_stock, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('title_ar', 'like', '%'.$request->search.'%')
                      ->orWhere('title_en', 'like', '%'.$request->search.'%');
            }))
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'products' => ProductResource::collection($products),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Get product details including inactive (Admin).
     * GET /api/v1/admin/products/{id}
     */
    public function adminShow(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view product details.');
        }

        $product = Product::with(['images', 'allPaymentOptions', 'allResalePlans'])->find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        // Return with all options including inactive
        return $this->successResponse([
            'id' => $product->id,
            'title_ar' => $product->title_ar,
            'title_en' => $product->title_en,
            'description_ar' => $product->description_ar,
            'description_en' => $product->description_en,
            'type' => $product->type,
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->in_stock,
            'is_active' => $product->is_active,
            'main_image_url' => $product->main_image ? route('products.main-image', $product->id) : null,
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => route('products.image', ['productId' => $product->id, 'imageId' => $image->id]),
                'sort_order' => $image->sort_order,
            ]),
            'payment_options' => $product->allPaymentOptions->map(fn ($option) => [
                'id' => $option->id,
                'type' => $option->type,
                'label' => $option->getDisplayLabel(),
                'is_active' => $option->is_active,
            ]),
            'resale_plans' => $product->allResalePlans->map(fn ($plan) => [
                'id' => $plan->id,
                'months' => $plan->months,
                'profit_percentage' => $plan->profit_percentage,
                'label' => $plan->getDisplayLabel(),
                'is_active' => $plan->is_active,
                'expected_return' => $plan->calculateExpectedReturn($product->price),
                'profit_amount' => $plan->calculateProfit($product->price),
            ]),
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ]);
    }

    /**
     * Create a new product (Admin).
     * POST /api/v1/admin/products
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $validated = $request->validated();

            // Handle main image upload
            $mainImage = null;
            $mainImageMimeType = null;
            if ($request->hasFile('main_image')) {
                $file = $request->file('main_image');
                $mainImage = file_get_contents($file->getRealPath());
                $mainImageMimeType = $file->getMimeType();
            }

            // Create product
            $product = Product::create([
                'title_ar' => $validated['title_ar'],
                'title_en' => $validated['title_en'],
                'description_ar' => $validated['description_ar'] ?? null,
                'description_en' => $validated['description_en'] ?? null,
                'type' => $validated['type'],
                'price' => $validated['price'],
                'stock_quantity' => $validated['stock_quantity'] ?? 0,
                'in_stock' => ($validated['stock_quantity'] ?? 0) > 0,
                'is_active' => $validated['is_active'] ?? true,
                'main_image' => $mainImage,
                'main_image_mime_type' => $mainImageMimeType,
            ]);

            // Create payment options
            if (! empty($validated['payment_options'])) {
                foreach ($validated['payment_options'] as $option) {
                    $product->allPaymentOptions()->create([
                        'type' => $option['type'],
                        'label' => $option['label'] ?? null,
                        'is_active' => $option['is_active'] ?? true,
                    ]);
                }
            }

            // Create resale plans
            if (! empty($validated['resale_plans'])) {
                foreach ($validated['resale_plans'] as $plan) {
                    $product->allResalePlans()->create([
                        'months' => $plan['months'],
                        'profit_percentage' => $plan['profit_percentage'],
                        'label' => $plan['label'] ?? null,
                        'is_active' => $plan['is_active'] ?? true,
                    ]);
                }
            }

            // Build response manually to avoid UTF-8 issues
            $responseData = [
                'message' => 'Product created successfully',
                'status' => '201',
                'data' => [
                    'id' => $product->id,
                    'title_ar' => mb_convert_encoding($product->title_ar, 'UTF-8', 'UTF-8'),
                    'title_en' => mb_convert_encoding($product->title_en, 'UTF-8', 'UTF-8'),
                    'description_ar' => $product->description_ar ? mb_convert_encoding($product->description_ar, 'UTF-8', 'UTF-8') : null,
                    'description_en' => $product->description_en ? mb_convert_encoding($product->description_en, 'UTF-8', 'UTF-8') : null,
                    'type' => $product->type,
                    'price' => (float) $product->price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'is_active' => (bool) $product->is_active,
                    'main_image_url' => $mainImage ? route('products.main-image', $product->id) : null,
                ],
            ];

            $json = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            return response($json, 201)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $errorJson = json_encode([
                'message' => 'Failed to create product: ' . $e->getMessage(),
                'status' => '500',
            ], JSON_INVALID_UTF8_SUBSTITUTE);

            return response($errorJson, 500)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Update a product (Admin).
     * PUT/PATCH /api/v1/admin/products/{id}
     */
    public function update(UpdateProductRequest $request, int $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $validated = $request->validated();

        // Handle main image upload
        if ($request->hasFile('main_image')) {
            $file = $request->file('main_image');
            $validated['main_image'] = file_get_contents($file->getRealPath());
            $validated['main_image_mime_type'] = $file->getMimeType();
        }

        // Update product fields
        $updateData = array_filter([
            'title_ar' => $validated['title_ar'] ?? null,
            'title_en' => $validated['title_en'] ?? null,
            'description_ar' => array_key_exists('description_ar', $validated) ? $validated['description_ar'] : null,
            'description_en' => array_key_exists('description_en', $validated) ? $validated['description_en'] : null,
            'type' => $validated['type'] ?? null,
            'price' => $validated['price'] ?? null,
            'stock_quantity' => $validated['stock_quantity'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'main_image' => $validated['main_image'] ?? null,
            'main_image_mime_type' => $validated['main_image_mime_type'] ?? null,
        ], fn ($value) => $value !== null);

        // Auto-set in_stock based on stock_quantity if stock_quantity is being updated
        if (isset($updateData['stock_quantity'])) {
            $updateData['in_stock'] = $updateData['stock_quantity'] > 0;
        }

        $product->update($updateData);

        // Update payment options if provided
        if (isset($validated['payment_options'])) {
            $existingIds = [];
            foreach ($validated['payment_options'] as $option) {
                if (! empty($option['id'])) {
                    // Update existing by ID
                    $paymentOption = $product->allPaymentOptions()->where('id', $option['id'])->first();
                    if ($paymentOption) {
                        $paymentOption->update([
                            'type' => $option['type'],
                            'label' => $option['label'] ?? null,
                            'is_active' => $option['is_active'] ?? true,
                        ]);
                        $existingIds[] = $paymentOption->id;
                    }
                } else {
                    // Use updateOrCreate to avoid duplicates based on product_id + type
                    $paymentOption = $product->allPaymentOptions()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'type' => $option['type'],
                        ],
                        [
                            'label' => $option['label'] ?? null,
                            'is_active' => $option['is_active'] ?? true,
                        ]
                    );
                    $existingIds[] = $paymentOption->id;
                }
            }
            // Delete removed options
            $product->allPaymentOptions()->whereNotIn('id', $existingIds)->delete();
        }

        // Update resale plans if provided
        if (isset($validated['resale_plans'])) {
            $existingIds = [];
            foreach ($validated['resale_plans'] as $plan) {
                if (! empty($plan['id'])) {
                    // Update existing by ID
                    $resalePlan = $product->allResalePlans()->where('id', $plan['id'])->first();
                    if ($resalePlan) {
                        $resalePlan->update([
                            'months' => $plan['months'],
                            'profit_percentage' => $plan['profit_percentage'],
                            'label' => $plan['label'] ?? null,
                            'is_active' => $plan['is_active'] ?? true,
                        ]);
                        $existingIds[] = $resalePlan->id;
                    }
                } else {
                    // Use updateOrCreate to avoid duplicates based on product_id + months
                    $resalePlan = $product->allResalePlans()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'months' => $plan['months'],
                        ],
                        [
                            'profit_percentage' => $plan['profit_percentage'],
                            'label' => $plan['label'] ?? null,
                            'is_active' => $plan['is_active'] ?? true,
                        ]
                    );
                    $existingIds[] = $resalePlan->id;
                }
            }
            // Delete removed plans
            $product->allResalePlans()->whereNotIn('id', $existingIds)->delete();
        }

        $product->load(['allPaymentOptions', 'allResalePlans']);

        return $this->successResponse(
            new ProductDetailResource($product),
            'Product updated successfully'
        );
    }

    /**
     * Delete a product (Admin).
     * DELETE /api/v1/admin/products/{id}
     */
    public function destroy(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete products.');
        }

        $product = Product::find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $product->delete();

        return $this->successResponse([], 'Product deleted successfully');
    }

    /**
     * Add images to a product (Admin).
     * POST /api/v1/admin/products/{id}/images
     */
    public function addImages(Request $request, int $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can add product images.');
        }

        $product = Product::find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $lastOrder = $product->images()->max('sort_order') ?? 0;
        $addedImages = [];

        foreach ($request->file('images') as $index => $file) {
            $image = $product->images()->create([
                'image' => file_get_contents($file->getRealPath()),
                'mime_type' => $file->getMimeType(),
                'sort_order' => $lastOrder + $index + 1,
            ]);
            $addedImages[] = [
                'id' => $image->id,
                'url' => route('products.image', ['productId' => $product->id, 'imageId' => $image->id]),
                'sort_order' => $image->sort_order,
            ];
        }

        return $this->createdResponse([
            'images' => $addedImages,
        ], 'Images added successfully');
    }

    /**
     * Delete a product image (Admin).
     * DELETE /api/v1/admin/products/{productId}/images/{imageId}
     */
    public function deleteImage(Request $request, int $productId, int $imageId)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete product images.');
        }

        $product = Product::find($productId);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $image = $product->images()->find($imageId);

        if (! $image) {
            return $this->notFoundResponse('Image not found');
        }

        $image->delete();

        return $this->successResponse([], 'Image deleted successfully');
    }

    // ==================== Product Images Base64 Endpoint ====================

    /**
     * Get all product images as Base64 (main + sub-images).
     * GET /api/v1/products/{id}/images/base64
     */
    public function getAllImagesBase64(int $id)
    {
        $product = Product::with('images')->active()->find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        $images = [];

        // Main image
        if ($product->main_image) {
            $mimeType = $product->main_image_mime_type ?? 'image/jpeg';
            $images[] = [
                'type' => 'main',
                'id' => null,
                'base64' => 'data:'.$mimeType.';base64,'.base64_encode($product->main_image),
                'mime_type' => $mimeType,
            ];
        }

        // Sub images
        foreach ($product->images as $image) {
            $mimeType = $image->mime_type ?? 'image/jpeg';
            $images[] = [
                'type' => 'sub',
                'id' => $image->id,
                'base64' => 'data:'.$mimeType.';base64,'.base64_encode($image->image),
                'mime_type' => $mimeType,
                'sort_order' => $image->sort_order,
            ];
        }

        return $this->successResponse([
            'product_id' => $product->id,
            'title' => $product->title,
            'images' => $images,
            'total_images' => count($images),
        ]);
    }

    // ==================== Favorites Endpoints ====================

    /**
     * Toggle product favorite status (Authenticated users only).
     * POST /api/v1/products/{id}/favorite
     */
    public function toggleFavorite(Request $request, int $id)
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $product = Product::active()->find($id);

        if (! $product) {
            return $this->notFoundResponse('Product not found');
        }

        // Check if already favorited
        $existingFavorite = $user->productFavorites()->where('product_id', $product->id)->first();

        if ($existingFavorite) {
            // Remove from favorites
            $existingFavorite->delete();

            return $this->successResponse([
                'product_id' => $product->id,
                'is_favorited' => false,
            ], 'Product removed from favorites');
        }

        // Add to favorites
        $user->productFavorites()->create([
            'product_id' => $product->id,
        ]);

        return $this->createdResponse([
            'product_id' => $product->id,
            'is_favorited' => true,
        ], 'Product added to favorites');
    }

    /**
     * Get user's favorite products (Authenticated users only).
     * GET /api/v1/products/favorites
     */
    public function getFavorites(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->errorResponse('Authentication required', 401);
        }

        $favorites = $user->favoriteProducts()
            ->active()
            ->orderBy('product_favorites.created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->successResponse([
            'products' => ProductResource::collection($favorites),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ],
        ]);
    }
}
