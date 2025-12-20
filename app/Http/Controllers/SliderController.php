<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Slider;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all sliders (Admin only).
     * GET /api/v1/admin/sliders
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view all sliders.');
        }

        $sliders = Slider::ordered()->get()->map(function ($slider) {
            return [
                'id' => $slider->id,
                'title' => $slider->title,
                'title_ar' => $slider->title_ar,
                'description' => $slider->description,
                'description_ar' => $slider->description_ar,
                'image' => $slider->image, // Already base64
                'is_active' => $slider->is_active,
                'order' => $slider->order,
                'created_at' => $slider->created_at,
                'updated_at' => $slider->updated_at,
            ];
        });

        return $this->successResponse([
            'sliders' => $sliders,
        ]);
    }

    /**
     * Get active sliders (Public - for hero display).
     * GET /api/v1/sliders
     */
    public function active()
    {
        $sliders = Slider::active()->ordered()->get()->map(function ($slider) {
            return [
                'id' => $slider->id,
                'title' => $slider->title,
                'title_ar' => $slider->title_ar,
                'description' => $slider->description,
                'description_ar' => $slider->description_ar,
                'image' => $slider->image,
                'is_active' => $slider->is_active,
                'order' => $slider->order,
            ];
        });

        return $this->successResponse([
            'sliders' => $sliders,
        ]);
    }

    /**
     * Store a new slider (Admin only).
     * POST /api/v1/admin/sliders
     */
    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can create sliders.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imageData = file_get_contents($request->file('image')->getRealPath());
            $mimeType = $request->file('image')->getMimeType();
            $validated['image'] = 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        }

        // Set default order if not provided
        if (! isset($validated['order'])) {
            $validated['order'] = Slider::max('order') + 1;
        }

        $slider = Slider::create($validated);

        return $this->createdResponse([
            'id' => $slider->id,
            'title' => $slider->title,
            'title_ar' => $slider->title_ar,
            'description' => $slider->description,
            'description_ar' => $slider->description_ar,
            'image' => $slider->image,
            'is_active' => $slider->is_active,
            'order' => $slider->order,
        ], 'Slider created successfully');
    }

    /**
     * Update a slider (Admin only).
     * PUT/PATCH /api/v1/admin/sliders/{id}
     */
    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update sliders.');
        }

        $slider = Slider::find($id);

        if (! $slider) {
            return $this->notFoundResponse('Slider not found');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'title_ar' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imageData = file_get_contents($request->file('image')->getRealPath());
            $mimeType = $request->file('image')->getMimeType();
            $validated['image'] = 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        }

        $slider->update($validated);

        return $this->successResponse([
            'id' => $slider->id,
            'title' => $slider->title,
            'title_ar' => $slider->title_ar,
            'description' => $slider->description,
            'description_ar' => $slider->description_ar,
            'image' => $slider->image,
            'is_active' => $slider->is_active,
            'order' => $slider->order,
        ], 'Slider updated successfully');
    }

    /**
     * Toggle slider active status (Admin only).
     * POST /api/v1/admin/sliders/{id}/toggle
     */
    public function toggle(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can toggle slider status.');
        }

        $slider = Slider::find($id);

        if (! $slider) {
            return $this->notFoundResponse('Slider not found');
        }

        $slider->is_active = ! $slider->is_active;
        $slider->save();

        return $this->successResponse([
            'id' => $slider->id,
            'is_active' => $slider->is_active,
        ], 'Slider status toggled successfully');
    }

    /**
     * Reorder sliders (Admin only).
     * POST /api/v1/admin/sliders/reorder
     */
    public function reorder(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can reorder sliders.');
        }

        $validated = $request->validate([
            'sliders' => 'required|array',
            'sliders.*.id' => 'required|integer|exists:sliders,id',
            'sliders.*.order' => 'required|integer',
        ]);

        foreach ($validated['sliders'] as $sliderData) {
            Slider::where('id', $sliderData['id'])->update(['order' => $sliderData['order']]);
        }

        return $this->successResponse(null, 'Sliders reordered successfully');
    }

    /**
     * Delete a slider (Admin only).
     * DELETE /api/v1/admin/sliders/{id}
     */
    public function destroy(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete sliders.');
        }

        $slider = Slider::find($id);

        if (! $slider) {
            return $this->notFoundResponse('Slider not found');
        }

        $slider->delete();

        return $this->successResponse(null, 'Slider deleted successfully');
    }
}
