<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\Marquee;
use Illuminate\Http\Request;

class MarqueeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all marquees (Admin only).
     * GET /api/v1/admin/marquees
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view all marquees.');
        }

        $marquees = Marquee::ordered()->get();

        return $this->successResponse([
            'marquees' => $marquees,
        ]);
    }

    /**
     * Get active marquees (Public - for banner display).
     * GET /api/v1/marquees
     */
    public function active()
    {
        $marquees = Marquee::active()->ordered()->get();

        return $this->successResponse([
            'marquees' => $marquees,
        ]);
    }

    /**
     * Store a new marquee (Admin only).
     * POST /api/v1/admin/marquees
     */
    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can create marquees.');
        }

        $validated = $request->validate([
            'text_ar' => 'required|string|max:500',
            'text_en' => 'required|string|max:500',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        // Set default order if not provided
        if (! isset($validated['order'])) {
            $validated['order'] = Marquee::max('order') + 1;
        }

        $marquee = Marquee::create($validated);

        return $this->createdResponse($marquee, 'Marquee created successfully');
    }

    /**
     * Update a marquee (Admin only).
     * PUT/PATCH /api/v1/admin/marquees/{id}
     */
    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update marquees.');
        }

        $marquee = Marquee::find($id);

        if (! $marquee) {
            return $this->notFoundResponse('Marquee not found');
        }

        $validated = $request->validate([
            'text_ar' => 'sometimes|required|string|max:500',
            'text_en' => 'sometimes|required|string|max:500',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        $marquee->update($validated);

        return $this->successResponse($marquee, 'Marquee updated successfully');
    }

    /**
     * Toggle marquee active status (Admin only).
     * POST /api/v1/admin/marquees/{id}/toggle
     */
    public function toggle(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can toggle marquee status.');
        }

        $marquee = Marquee::find($id);

        if (! $marquee) {
            return $this->notFoundResponse('Marquee not found');
        }

        $marquee->is_active = ! $marquee->is_active;
        $marquee->save();

        return $this->successResponse($marquee, 'Marquee status toggled successfully');
    }

    /**
     * Delete a marquee (Admin only).
     * DELETE /api/v1/admin/marquees/{id}
     */
    public function destroy(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete marquees.');
        }

        $marquee = Marquee::find($id);

        if (! $marquee) {
            return $this->notFoundResponse('Marquee not found');
        }

        // Ensure at least one marquee exists
        if (Marquee::count() <= 1) {
            return $this->errorResponse('Cannot delete the last marquee. At least one is required.', 400);
        }

        $marquee->delete();

        return $this->successResponse(null, 'Marquee deleted successfully');
    }
}
