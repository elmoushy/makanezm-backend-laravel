<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHeroProductsCoverRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\HeroSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HeroSettingController extends Controller
{
    use ApiResponseTrait;

    /**
     * Compress image to be under 1 MB.
     */
    private function compressImage($imageData, $mimeType, $maxSizeBytes = 1048576)
    {
        // If already under 1 MB, return as is
        if (strlen($imageData) <= $maxSizeBytes) {
            return $imageData;
        }

        // Create image resource based on mime type
        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            // If image creation fails, return original (validation will catch issues)
            return $imageData;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Start with quality 85 and reduce until under 1 MB
        $quality = 85;
        $compressed = null;

        while ($quality > 10) {
            ob_start();

            // Compress based on mime type
            if (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg')) {
                imagejpeg($image, null, $quality);
            } elseif (str_contains($mimeType, 'png')) {
                // PNG compression level (0-9, inverted quality scale)
                $pngQuality = (int) round((100 - $quality) / 11);
                imagepng($image, null, $pngQuality);
            } elseif (str_contains($mimeType, 'gif')) {
                imagegif($image, null);
            } elseif (str_contains($mimeType, 'webp')) {
                imagewebp($image, null, $quality);
            } else {
                // Default to JPEG for unknown types
                imagejpeg($image, null, $quality);
            }

            $compressed = ob_get_clean();

            // Check if compressed size is under limit
            if (strlen($compressed) <= $maxSizeBytes) {
                imagedestroy($image);
                return $compressed;
            }

            // Reduce quality for next iteration
            $quality -= 10;
        }

        // If still too large, resize the image
        $scale = sqrt($maxSizeBytes / strlen($compressed));
        $newWidth = (int) ($width * $scale * 0.9); // 0.9 for safety margin
        $newHeight = (int) ($height * $scale * 0.9);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if (str_contains($mimeType, 'png') || str_contains($mimeType, 'gif')) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        if (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg')) {
            imagejpeg($resized, null, 75);
        } elseif (str_contains($mimeType, 'png')) {
            imagepng($resized, null, 6);
        } elseif (str_contains($mimeType, 'gif')) {
            imagegif($resized, null);
        } elseif (str_contains($mimeType, 'webp')) {
            imagewebp($resized, null, 75);
        } else {
            imagejpeg($resized, null, 75);
        }
        $compressed = ob_get_clean();

        imagedestroy($image);
        imagedestroy($resized);

        return $compressed;
    }

    /**
     * Helper to format image as Base64 data URI.
     */
    private function formatImageAsBase64($data, $mimeType)
    {
        if (!$data) return null;
        return 'data:' . ($mimeType ?? 'image/png') . ';base64,' . base64_encode($data);
    }

    /**
     * Helper to process image input (file or base64 string).
     */
    private function processImageInput($request, $field, &$validated)
    {
        if ($request->hasFile($field)) {
            $file = $request->file($field);
            $imageData = file_get_contents($file->getRealPath());
            $mimeType = $file->getMimeType();

            // Compress image to under 1 MB
            $imageData = $this->compressImage($imageData, $mimeType);

            $validated[$field] = $imageData;
            if (Schema::hasColumn('hero_settings', $field . '_mime_type')) {
                $validated[$field . '_mime_type'] = $mimeType;
            }
        } elseif ($request->filled($field) && is_string($request->input($field))) {
            $base64String = $request->input($field);
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String)) {
                $base64Data = substr($base64String, strpos($base64String, ',') + 1);
                $decodedData = base64_decode($base64Data);
                if ($decodedData !== false) {
                    // Detect MIME type
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($decodedData);

                    // Compress image to under 1 MB
                    $decodedData = $this->compressImage($decodedData, $mimeType);

                    $validated[$field] = $decodedData;
                    if (Schema::hasColumn('hero_settings', $field . '_mime_type')) {
                        $validated[$field . '_mime_type'] = $mimeType;
                    }
                }
            }
        }
    }

    /**
     * Get the current hero setting (Admin only).
     * GET /api/v1/admin/hero
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view hero settings.');
        }

        $hero = HeroSetting::first();

        if (! $hero) {
            return $this->successResponse([
                'hero' => null,
            ]);
        }

        return $this->successResponse([
            'hero' => [
                'id' => $hero->id,
                'title' => $hero->title,
                'title_ar' => $hero->title_ar,
                'description1' => $hero->description1,
                'description1_ar' => $hero->description1_ar,
                'description2' => $hero->description2,
                'description2_ar' => $hero->description2_ar,
                'image' => $this->formatImageAsBase64($hero->image, $hero->image_mime_type),
                'service_image' => $this->formatImageAsBase64($hero->service_image, $hero->service_image_mime_type),
                'products_cover_image' => $this->formatImageAsBase64($hero->products_cover_image, $hero->products_cover_image_mime_type),
                'image_url' => $hero->image ? route('hero.image') : null,
                'service_image_url' => $hero->service_image ? route('hero.service-image') : null,
                'products_cover_image_url' => $hero->products_cover_image ? route('hero.products-cover-image') : null,
                'is_active' => $hero->is_active,
                'created_at' => $hero->created_at,
                'updated_at' => $hero->updated_at,
            ],
        ]);
    }

    /**
     * Get active hero setting (Public - for hero display).
     * GET /api/v1/hero
     */
    public function active()
    {
        $hero = HeroSetting::active()->first();

        if (! $hero) {
            return $this->successResponse([
                'hero' => null,
            ]);
        }

        return $this->successResponse([
            'hero' => [
                'id' => $hero->id,
                'title' => $hero->title,
                'title_ar' => $hero->title_ar,
                'description1' => $hero->description1,
                'description1_ar' => $hero->description1_ar,
                'description2' => $hero->description2,
                'description2_ar' => $hero->description2_ar,
                'image' => $this->formatImageAsBase64($hero->image, $hero->image_mime_type),
                'service_image' => $this->formatImageAsBase64($hero->service_image, $hero->service_image_mime_type),
                'products_cover_image' => $this->formatImageAsBase64($hero->products_cover_image, $hero->products_cover_image_mime_type),
                'image_url' => $hero->image ? route('hero.image') : null,
                'service_image_url' => $hero->service_image ? route('hero.service-image') : null,
                'products_cover_image_url' => $hero->products_cover_image ? route('hero.products-cover-image') : null,
                'is_active' => $hero->is_active,
            ],
        ]);
    }

    /**
     * Update products cover image only (Admin only).
     * POST /api/v1/admin/hero/products-cover
     */
    public function updateProductsCover(UpdateHeroProductsCoverRequest $request)
    {
        // Authorization is handled by the FormRequest.

        $imageData = null;
        $mimeType = null;

        if ($request->hasFile('products_cover_image')) {
            $file = $request->file('products_cover_image');
            $imageData = file_get_contents($file->getRealPath());
            $mimeType = $file->getMimeType();
        } elseif ($request->filled('products_cover_image') && is_string($request->input('products_cover_image'))) {
            $base64String = $request->input('products_cover_image');

            // Check if it looks like a data URI
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String)) {
                $base64Data = substr($base64String, strpos($base64String, ',') + 1);
                $decodedData = base64_decode($base64Data);

                if ($decodedData !== false) {
                    $imageData = $decodedData;
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($imageData);
                }
            }
        }

        if ($imageData === null) {
            return $this->errorResponse('Invalid image provided', 422);
        }

        // Compress image to under 1 MB
        $imageData = $this->compressImage($imageData, $mimeType);

        // Get existing hero or create new one (only one record allowed)
        $hero = HeroSetting::first();

        $payload = [
            'products_cover_image' => $imageData,
        ];

        if (Schema::hasColumn('hero_settings', 'products_cover_image_mime_type')) {
            $payload['products_cover_image_mime_type'] = $mimeType;
        }

        if ($hero) {
            $hero->update($payload);
        } else {
            $hero = HeroSetting::create(array_merge([
                'title' => 'Default Title',
                'title_ar' => 'Default Title',
            ], $payload));
        }

        return $this->successResponse([
            'id' => $hero->id,
            'products_cover_image_url' => route('hero.products-cover-image'),
        ]);
    }

    /**
     * Create or update hero setting (Admin only).
     * POST /api/v1/admin/hero
     */
    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can manage hero settings.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description1' => 'nullable|string',
            'description1_ar' => 'nullable|string',
            'description2' => 'nullable|string',
            'description2_ar' => 'nullable|string',
            'image' => 'nullable',
            'service_image' => 'nullable',
            'products_cover_image' => 'nullable',
            'is_active' => 'sometimes|boolean',
        ]);

        $this->processImageInput($request, 'image', $validated);
        $this->processImageInput($request, 'service_image', $validated);
        $this->processImageInput($request, 'products_cover_image', $validated);

        // Get existing hero or create new one (only one record allowed)
        $hero = HeroSetting::first();

        if ($hero) {
            $hero->update($validated);
        } else {
            $hero = HeroSetting::create($validated);
        }

        return $this->successResponse([
            'id' => $hero->id,
            'title' => $hero->title,
            'title_ar' => $hero->title_ar,
            'description1' => $hero->description1,
            'description1_ar' => $hero->description1_ar,
            'description2' => $hero->description2,
            'description2_ar' => $hero->description2_ar,
            'image' => $this->formatImageAsBase64($hero->image, $hero->image_mime_type),
            'service_image' => $this->formatImageAsBase64($hero->service_image, $hero->service_image_mime_type),
            'products_cover_image' => $this->formatImageAsBase64($hero->products_cover_image, $hero->products_cover_image_mime_type),
            'image_url' => $hero->image ? route('hero.image') : null,
            'service_image_url' => $hero->service_image ? route('hero.service-image') : null,
            'products_cover_image_url' => $hero->products_cover_image ? route('hero.products-cover-image') : null,
            'is_active' => $hero->is_active,
        ], $hero->wasRecentlyCreated ? 'Hero setting created successfully' : 'Hero setting updated successfully');
    }

    /**
     * Update hero setting (Admin only).
     * PUT/PATCH /api/v1/admin/hero/{id}
     */
    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update hero settings.');
        }

        $hero = HeroSetting::find($id);

        if (! $hero) {
            return $this->notFoundResponse('Hero setting not found');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'sometimes|required|string|max:255',
            'description1' => 'nullable|string',
            'description1_ar' => 'nullable|string',
            'description2' => 'nullable|string',
            'description2_ar' => 'nullable|string',
            'image' => 'nullable',
            'service_image' => 'nullable',
            'products_cover_image' => 'nullable',
            'is_active' => 'sometimes|boolean',
        ]);

        $this->processImageInput($request, 'image', $validated);
        $this->processImageInput($request, 'service_image', $validated);
        $this->processImageInput($request, 'products_cover_image', $validated);

        $hero->update($validated);

        return $this->successResponse([
            'id' => $hero->id,
            'title' => $hero->title,
            'title_ar' => $hero->title_ar,
            'description1' => $hero->description1,
            'description1_ar' => $hero->description1_ar,
            'description2' => $hero->description2,
            'description2_ar' => $hero->description2_ar,
            'image' => $this->formatImageAsBase64($hero->image, $hero->image_mime_type),
            'service_image' => $this->formatImageAsBase64($hero->service_image, $hero->service_image_mime_type),
            'products_cover_image' => $this->formatImageAsBase64($hero->products_cover_image, $hero->products_cover_image_mime_type),
            'image_url' => $hero->image ? route('hero.image') : null,
            'service_image_url' => $hero->service_image ? route('hero.service-image') : null,
            'products_cover_image_url' => $hero->products_cover_image ? route('hero.products-cover-image') : null,
            'is_active' => $hero->is_active,
        ], 'Hero setting updated successfully');
    }

    /**
     * Toggle hero setting status (Admin only).
     * POST /api/v1/admin/hero/{id}/toggle
     */
    public function toggle(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can toggle hero settings.');
        }

        $hero = HeroSetting::find($id);

        if (! $hero) {
            return $this->notFoundResponse('Hero setting not found');
        }

        $hero->is_active = ! $hero->is_active;
        $hero->save();

        return $this->successResponse([
            'id' => $hero->id,
            'is_active' => $hero->is_active,
        ], 'Hero setting '.($hero->is_active ? 'activated' : 'deactivated').' successfully');
    }

    /**
     * Serve hero main image as binary.
     * GET /api/v1/hero/image
     */
    public function image()
    {
        $hero = HeroSetting::first();

        if (! $hero || ! $hero->image) {
            return response('Image not found', 404);
        }

        return response($hero->image)
            ->header('Content-Type', $hero->image_mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve hero service image as binary.
     * GET /api/v1/hero/service-image
     */
    public function serviceImage()
    {
        $hero = HeroSetting::first();

        if (! $hero || ! $hero->service_image) {
            return response('Image not found', 404);
        }

        return response($hero->service_image)
            ->header('Content-Type', $hero->service_image_mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve hero products cover image as binary.
     * GET /api/v1/hero/products-cover-image
     */
    public function productsCoverImage()
    {
        $hero = HeroSetting::first();

        if (! $hero || ! $hero->products_cover_image) {
            return response('Image not found', 404);
        }

        return response($hero->products_cover_image)
            ->header('Content-Type', $hero->products_cover_image_mime_type ?? 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
